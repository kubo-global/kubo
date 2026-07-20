<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\NatConfig;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Click-through scorebook: a current-year class list, a per-class read-only
 * overview (subject tabs + term switcher), and the class-level NAT page.
 * Replaces the reactive Livewire Grades dropdown cascade. Add/edit stays on the
 * existing dedicated screens.
 */
class ScorebookController extends Controller
{
    /** Class list, defaulting to the current school year. */
    public function index(Request $request)
    {
        $schoolyears = Schoolyear::orderBy('start', 'desc')->get();
        $currentId = Schoolyear::current()?->id ?? $schoolyears->first()?->id;
        $selectedYear = $schoolyears->firstWhere('id', (int) $request->query('year')) ?? $schoolyears->firstWhere('id', $currentId);

        $offerings = $selectedYear
            ? Offering::tagSectionIndex($this->offeringsFor($selectedYear->id)->loadCount('enrollments')->load('grade'))
            : collect();

        return view('pages.scorebook.classes', [
            'schoolyears' => $schoolyears,
            'selectedYear' => $selectedYear,
            'currentYearId' => $currentId,
            // School reading order: nursery stages first, then grades, then section.
            'offerings' => $offerings->sortBy(fn ($o) => Grade::sortKey($o->grade?->name).'-'.$o->name)->values(),
        ]);
    }

    /** Read-only overview of one class for a subject + term (Test/Exam only). */
    public function class(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $term = $this->resolveTerm($offering, (int) $request->query('term'));
        // Base order = the term report's (the curriculum order the subjects were attached in).
        $subjects = $term ? $offering->subjects($term->id)->get() : collect();

        // How many tests/exams each subject has this term (for the tab count badges).
        $subjectAssessmentCounts = $term
            ? Assessment::where('offering_id', $offering->id)->where('term_id', $term->id)
                ->selectRaw('subject_id, count(*) as c')->groupBy('subject_id')->pluck('c', 'subject_id')
            : collect();

        // Lead with subjects that already have scores (and so the default lands on one with
        // data), keeping the curriculum order within each group. sortBy is stable on PHP 8+.
        $subjects = $subjects->sortByDesc(fn ($s) => ($subjectAssessmentCounts[$s->id] ?? 0) > 0 ? 1 : 0)->values();

        $subject = $subjects->firstWhere('id', (int) $request->query('subject')) ?? $subjects->first();

        $assessments = ($subject && $term)
            ? Assessment::where('offering_id', $offering->id)
                ->where('subject_id', $subject->id)
                ->where('term_id', $term->id)              // term-less NAT is excluded by design
                ->with('assessmentType')
                ->orderBy('assessment_type_id')->orderBy('date')->get()
            : collect();

        return view('pages.scorebook.class', [
            'offering' => $offering,
            'subjects' => $subjects,
            'subject' => $subject,
            'subjectAssessmentCounts' => $subjectAssessmentCounts,
            'terms' => $offering->schoolyear?->terms()->orderBy('start')->get() ?? collect(),
            'term' => $term,
            'students' => $this->students($offering),
            'assessments' => $assessments,
            'scores' => $this->scoresFor($assessments),
            'natEnabled' => $this->natSitsThisClass($offering),
        ]);
    }

    /** The class-level NAT page: all NAT subjects together, term-less. */
    public function nat(Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $natType = AssessmentType::where('name', 'National Assessment Test')->first();

        $assessments = $natType
            ? Assessment::where('offering_id', $offering->id)
                ->where('assessment_type_id', $natType->id)
                ->with('subject', 'scores')
                ->get()->sortBy('subject.name')->values()
            : collect();

        return view('pages.scorebook.nat', [
            'offering' => $offering,
            'students' => $this->students($offering),
            'assessments' => $assessments,
            'scores' => $this->scoresFor($assessments),
            'natLabel' => $this->natLabel($offering),
            'natType' => $natType,
        ]);
    }

    // ---- helpers -----------------------------------------------------------

    private function offeringsFor(int $schoolyearId)
    {
        $user = Auth::user();

        // Headmaster and assistant coordinator oversee every class; teachers only their own.
        if ($user->hasAnyRole(['headmaster', 'assistant_coordinator'])) {
            return Offering::where('schoolyear_id', $schoolyearId)->get();
        }

        $teacher = Teacher::find($user->id);

        // Always an Eloquent collection (callers chain ->loadCount()); a non-teacher
        // admin simply has no classes of their own.
        return $teacher
            ? $teacher->offerings()->where('schoolyear_id', $schoolyearId)->get()
            : new \Illuminate\Database\Eloquent\Collection();
    }

    /**
     * Class positions: pupils ranked by their term Total (the same total the
     * report uses, so non-counting subjects are already excluded). Ties share a
     * position. Reuses the term-report aggregation as the single source of truth.
     */
    public function positions(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $term = $this->resolveTerm($offering, (int) $request->query('term'));

        $school = School::first();
        $ranked = $term
            ? (new \App\Domain\Reporting\Services\PositionService)->rank($offering, $term, $school)
            : collect();
        $incomplete = $term
            ? (new \App\Domain\Reporting\Services\ReportReadiness)->incompleteSubjects($offering, $term, $school)
            : collect();

        return view('pages.scorebook.positions', [
            'offering'   => $offering,
            'term'       => $term,
            'terms'      => $offering->schoolyear?->terms()->orderBy('start')->get() ?? collect(),
            'ranked'     => $ranked,
            'incomplete' => $incomplete,
        ]);
    }

    /** The class "Internal Assessment Result Sheet": pupils x subjects with totals, for a term. */
    public function resultSheet(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $term = $this->resolveTerm($offering, (int) $request->query('term'));

        $ranked = $term
            ? (new \App\Domain\Reporting\Services\PositionService)->rankedReports($offering, $term, $school)
            : collect();

        $pdf = PDF::loadView('print.result-sheet', [
            'offering' => $offering,
            'school'   => $school,
            'term'     => $term,
            'teacher'  => $offering->principal->first(),
            // Calculated (counts toward total) subjects first, then graded ones — the
            // master-list layout schools post on the wall. Alphabetical within each group.
            'subjects' => $term
                ? $offering->subjects($term->id)->get()
                    ->sortBy('name')
                    ->sortBy(fn ($s) => $s->countsTowardTotalResolved() ? 0 : 1)
                    ->values()
                : collect(),
            // Roster order (by name) for the sheet; the Position column carries the ranking.
            'rows'     => $ranked->sortBy(fn ($e) => \Illuminate\Support\Str::lower($e['student']->last_name.' '.$e['student']->first_name))->values(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Result sheet '.$offering->displayName().($term ? ' '.$term->name : '').'.pdf');
    }

    private function resolveTerm(Offering $offering, int $requested): ?Term
    {
        $terms = $offering->schoolyear?->terms()->orderBy('start')->get() ?? collect();

        if ($requested && ($t = $terms->firstWhere('id', $requested))) {
            return $t;
        }

        // Default to the term we are currently in (now within its dates); otherwise
        // the first term. (Term::current() can't be used: it has an end<=now bug.)
        $now = now();

        return $terms->first(fn ($t) => $t->start <= $now && $t->end >= $now) ?? $terms->first();
    }

    private function students(Offering $offering)
    {
        return $offering->enrollments()
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->orderBy('users.last_name')->orderBy('users.first_name')
            ->with('student')   // avoid an N+1 on $e->student below
            ->select('enrollments.*')->get()
            ->map(fn ($e) => $e->student)->filter()->values();
    }

    private function scoresFor($assessments): array
    {
        $ids = collect($assessments)->pluck('id')->all();
        $out = [];
        if ($ids) {
            foreach (AssessmentScore::whereIn('assessment_id', $ids)->get() as $s) {
                $out[$s->assessment_id.'_'.$s->user_id] = ['score' => $s->score, 'absent' => $s->absent];
            }
        }

        return $out;
    }

    private function natSitsThisClass(Offering $offering): bool
    {
        $school = School::first();
        if (! $school || ! $offering->schoolyear) {
            return false;
        }
        $config = NatConfig::for($school, $offering->schoolyear);

        // config-driven when present; otherwise fall back to "has NAT data"
        if ($config) {
            return $config->gradeSits($offering->grade_id);
        }

        $natType = AssessmentType::where('name', 'National Assessment Test')->first();

        return $natType && Assessment::where('offering_id', $offering->id)
            ->where('assessment_type_id', $natType->id)->exists();
    }

    private function natLabel(Offering $offering): string
    {
        $school = School::first();
        $config = $school && $offering->schoolyear ? NatConfig::for($school, $offering->schoolyear) : null;

        return $config->label ?? 'National Assessment Test';
    }
}
