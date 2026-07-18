<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Domain\Reporting\Repositories\ClassTermReportRepository;
use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Domain\Reporting\Services\PositionService;
use App\Domain\Reporting\Services\ReportGeneratorService;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class ReportController extends Controller
{
    public function overview()
    {
        return view('pages.termreport.index');
    }

    private function resolveSchool(): School
    {
        return School::first();
    }

    /**
     * The one-page term-card template for this school. Per-school report format:
     * schools keep their own layout (e.g. Albreda's WAEC card, The Swallow's
     * auto-filled card). Default preserved.
     */
    private function termCardView(): string
    {
        return match ($this->resolveSchool()?->config(\App\Models\SchoolConfig::TERM_CARD_LAYOUT)) {
            'albreda' => 'print.termReport-albreda',
            'swallow' => 'print.termReport-swallow',
            default => 'print.termReport',
        };
    }

    /**
     * The extras only the Swallow card renders — position, class size, and the typed
     * conduct/general remark per pupil (keyed by student id) — computed once for the
     * class so a single card and a class bundle share the work. Kept out of
     * NewTermReportRepository so that stays a pure marks-only computation.
     *
     * @return array{positions:\Illuminate\Support\Collection,classSize:int,remarks:\Illuminate\Support\Collection}
     */
    private function swallowExtras(?Offering $offering, Term $term, School $school): array
    {
        if (! $offering) {
            return ['positions' => collect(), 'classSize' => 0, 'remarks' => collect()];
        }

        $ranked = (new PositionService())->rankedReports($offering, $term, $school)->keyBy('student_id');

        $enrollmentByUser = Enrollment::where('offering_id', $offering->id)->pluck('id', 'user_id');
        $saved = \App\Models\ReportRemark::where('term_id', $term->id)
            ->whereIn('enrollment_id', $enrollmentByUser->values())
            ->get()->keyBy('enrollment_id');
        $remarks = $enrollmentByUser->mapWithKeys(fn ($enrollmentId, $userId) => [$userId => [
            'conduct' => $saved[$enrollmentId]->conduct ?? null,
            'general' => $saved[$enrollmentId]->general_remarks ?? null,
        ]]);

        return ['positions' => $ranked, 'classSize' => $ranked->count(), 'remarks' => $remarks];
    }

    public function showTermReport($enrollmentId, $termId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $term = Term::findOrFail($termId);

        $termReport = new NewTermReportRepository($enrollment, $term, $this->resolveSchool());
        $reportService = new ReportGeneratorService();
        $reports = $reportService->generateStudentReportPdf($termReport);

        return view('pages.termreport.show', [
            'report' => $reports[0],
            'enrollmentId' => $enrollmentId,
            'termId' => $termId,
            'reportType' => $this->resolveSchool()?->config(\App\Models\SchoolConfig::REPORT_TYPE, 'term') ?? 'term',
        ]);
    }

    public function generateTermReport($enrollmentId, $termId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $term = Term::findOrFail($termId);
        $school = $this->resolveSchool();

        $termReport = new NewTermReportRepository($enrollment, $term, $school);
        $reportService = new ReportGeneratorService();
        $reports = $reportService->generateStudentReportPdf($termReport);

        $view = $this->termCardView();
        $extra = $view === 'print.termReport-swallow'
            ? $this->swallowExtras($enrollment->offering, $term, $school)
            : [];

        $pdf = PDF::loadView($view, ['reports' => $reports] + $extra)->setPaper('a5');

        return $pdf->download($reports[0]['student']->first_name . ' ' . $reports[0]['student']->last_name . '-results.pdf');
    }

    /**
     * The "Report Book": a term-collapsed annual grid for one pupil (terms down,
     * subjects across) mirroring the reusable paper booklet some schools keep.
     * ?empty=1 prints a blank template (structure only) for filling by hand.
     */
    public function reportBook(Request $request, $enrollmentId)
    {
        [$enrollment, $data] = $this->reportBookPayload($enrollmentId, $request->boolean('empty'));

        $pdf = PDF::loadView('print.reportBook', $data)->setPaper('a4', 'landscape');

        $name = trim($enrollment->student->first_name.' '.$enrollment->student->last_name);
        return $pdf->download($name.($data['empty'] ? ' - blank report book' : ' - report book').'.pdf');
    }

    /** The report book shown on screen (same layout as the PDF) with a Download-PDF button. */
    public function reportBookScreen(Request $request, $enrollmentId)
    {
        [, $data] = $this->reportBookPayload($enrollmentId, $request->boolean('empty'));

        // The PDF passes the logo as a filesystem path (for DomPDF); the browser needs a URL.
        $logoPath = $data['school']->logo_path ?? null;
        $data['logo'] = ($logoPath && file_exists(public_path($logoPath))) ? asset($logoPath) : null;

        return view('pages.report.report-book', $data + ['enrollmentId' => (int) $enrollmentId]);
    }

    /** Shared data assembly for the report book, used by both the PDF and the on-screen view. */
    private function reportBookPayload($enrollmentId, bool $empty): array
    {
        $enrollment = Enrollment::with(['offering.grade', 'offering.schoolyear', 'offering.principal', 'student'])
            ->findOrFail($enrollmentId);
        $offering = $enrollment->offering;
        $school = $this->resolveSchool();

        $ctx = $this->reportBookContext($offering, $school, $empty);
        $book = $this->assembleBook($enrollment->student, $offering, $ctx, $empty);

        return [$enrollment, $book + [
            'gradeKey' => $ctx['gradeKey'], 'school' => $school, 'logo' => $ctx['logo'], 'empty' => $empty,
        ]];
    }

    /**
     * Report books for a whole class in one PDF (one pupil per page). Reuses the
     * single computation per term (positions + per-subject totals) across pupils.
     */
    public function classReportBook(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = $this->resolveSchool();
        $empty = $request->boolean('empty');

        $ctx = $this->reportBookContext($offering, $school, $empty);

        $students = $offering->enrollments()
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->orderBy('users.last_name')->orderBy('users.first_name')
            ->select('enrollments.*')->with('student')->get()
            ->map(fn ($e) => $e->student)->filter()->values();

        $books = $students->map(fn ($student) => $this->assembleBook($student, $offering, $ctx, $empty));

        $pdf = PDF::loadView('print.reportBookBatch', [
            'books' => $books, 'gradeKey' => $ctx['gradeKey'], 'school' => $school, 'logo' => $ctx['logo'], 'empty' => $empty,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($offering->displayName().($empty ? ' - blank report books' : ' - report books').'.pdf');
    }

    /**
     * Shared per-class context for the report book: the year's terms, the union of
     * subjects, the logo, and (unless empty) each term's ranked reports keyed by
     * pupil — so positions and subject cells are computed once per term.
     */
    private function reportBookContext(Offering $offering, School $school, bool $empty): array
    {
        $terms = $offering->schoolyear?->terms()->orderBy('start')->get() ?? collect();

        $subjects = collect();
        foreach ($terms as $term) {
            $subjects = $subjects->merge($offering->subjects($term->id)->get());
        }
        $subjects = $subjects->unique('id')->values();

        $perTerm = [];
        if (! $empty) {
            $positions = new PositionService();
            foreach ($terms as $term) {
                $perTerm[$term->id] = $positions->rankedReports($offering, $term, $school)->keyBy('student_id');
            }
        }

        return [
            'terms'      => $terms,
            'subjects'   => $subjects,
            'perTerm'    => $perTerm,
            'attendance' => $empty ? collect() : AttendanceController::yearTotals($offering->id),
            'gradeKey'   => \App\Models\GradingScale::keyFor($school, (int) preg_replace('/\D/', '', $offering->grade->name ?? '')),
            'logo'       => ($school?->logo_path && file_exists(public_path($school->logo_path))) ? public_path($school->logo_path) : null,
        ];
    }

    /** Month rows shown under each term, matching the schools' paper booklet. */
    private const TERM_MONTHS = [
        ['Oct', 'Nov'],
        ['Jan', 'Feb'],
        ['Apr', 'May', 'June'],
    ];

    /**
     * Build one pupil's report-book rows: each term contributes its month rows
     * (blank — filled by hand) and an Exam row carrying KUBO's term result.
     */
    private function assembleBook($student, Offering $offering, array $ctx, bool $empty): array
    {
        $rows = [];
        foreach ($ctx['terms']->values() as $i => $term) {
            foreach (self::TERM_MONTHS[$i] ?? ['Month'] as $month) {
                $rows[] = ['label' => $month, 'exam' => false, 'cells' => [], 'total' => null, 'position' => null];
            }

            $cells = [];
            $total = $position = null;
            if (! $empty && ($entry = $ctx['perTerm'][$term->id][$student->id] ?? null)) {
                $results = $entry['report']['results'];
                foreach ($ctx['subjects'] as $s) {
                    $sr = $results['subjectResults'][$s->name] ?? null;
                    $cells[$s->id] = ($sr && $sr['subjectTotal'] !== null) ? round($sr['subjectTotal']) : null;
                }
                $total = $results['total'];
                $position = $entry['position'];
            }
            $rows[] = ['label' => 'Exam', 'exam' => true, 'cells' => $cells, 'total' => $total, 'position' => $position];
        }

        $att = $ctx['attendance'][$student->id] ?? null;

        return [
            'student'     => $student,
            'offering'    => $offering,
            'schoolyear'  => $offering->schoolyear,
            'subjects'    => $ctx['subjects'],
            'rows'        => $rows,
            'teacher'     => $offering->principal->first(),
            'timePresent' => $att ? (int) $att->present : null,
            'timeAbsent'  => $att ? (int) $att->absent : null,
        ];
    }

    public function generateClassTermReport($schoolyearId, $termId, $gradeId)
    {
        $term = Term::findOrFail($termId);
        $school = $this->resolveSchool();
        $reportService = new ReportGeneratorService();

        $enrollments = (new Offering())->enrolledFor($schoolyearId, $gradeId);
        $classTermReport = new ClassTermReportRepository($enrollments, $term, $school);

        $reports = $reportService->generateClassReportPdf($classTermReport);

        $view = $this->termCardView();
        $extra = $view === 'print.termReport-swallow'
            ? $this->swallowExtras(
                Offering::where('schoolyear_id', $schoolyearId)->where('grade_id', $gradeId)->first(),
                $term,
                $school,
            )
            : [];

        $pdf = PDF::loadView($view, ['reports' => $reports] + $extra)->setPaper('a5');

        return $pdf->download(Grade::find($gradeId)->name . '-results.pdf');
    }
}
