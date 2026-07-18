<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Offering;
use App\Models\Profile;
use App\Models\School;
use App\Models\Term;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "By month" scorebook view: one screen where a teacher types every pupil's mark
 * for every subject for a given month's test or exam (mirroring the rough-paper
 * step). A month is one assessment period, a Test or an Exam; each is stored as
 * one assessment per subject. Feeds the result sheet / analysis / histogram.
 */
class TermResultController extends Controller
{
    public function grid(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);

        // The read-only landing is the results view; the grid itself is only for editing.
        if (! $request->boolean('edit')) {
            return redirect()->route('term-grid.report', $this->periodParams($offering, $period));
        }

        $students = $this->students($offering);
        $subjects = $period['term'] ? $this->subjects($offering, $period['term']) : collect();

        // existing[subjectId][studentId] => mark, or '' when the pupil was absent.
        $existing = [];
        $exams = $this->periodExams($offering, $period)->keyBy('id');
        if ($exams->isNotEmpty()) {
            foreach (AssessmentScore::whereIn('assessment_id', $exams->keys())->get() as $s) {
                $subjectId = $exams[$s->assessment_id]->subject_id ?? null;
                if ($subjectId) {
                    $existing[$subjectId][$s->user_id] = $s->absent ? '' : $s->score;
                }
            }
        }

        $editableSubjects = $this->editableSubjectIds($offering, $request->user());

        return view('pages.scorebook.term-grid', compact('offering', 'school', 'students', 'subjects', 'existing', 'period', 'editableSubjects'));
    }

    /**
     * Subject ids the current user may edit marks for in this class, or null when
     * they may edit every subject: headmaster/admin/assistant coordinator, a class
     * teacher, or a teacher with no subject-specific assignment (the general case,
     * so existing schools are unaffected). Once a teacher is assigned specific
     * subjects for the class (Settings → subject teachers), they are limited to those.
     */
    private function editableSubjectIds(Offering $offering, $user): ?array
    {
        if ($user->hasAnyRole(['headmaster', 'admin', 'assistant_coordinator'])) {
            return null;
        }

        $rows = DB::table('teacher_assignments')
            ->where('offering_id', $offering->id)
            ->where('user_id', $user->id)
            ->get();

        if ($rows->isEmpty() || $rows->contains(fn ($r) => $r->is_class_teacher || $r->subject_id === null)) {
            return null;
        }

        return $rows->pluck('subject_id')->map(fn ($v) => (int) $v)->all();
    }

    public function save(Request $request, Offering $offering)
    {
        $offering->load('schoolyear');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'] && $period['date'], 400);

        $request->validate([
            'type' => 'nullable|in:Test,Exam',
            'scores' => 'array',
            'scores.*' => 'array',
            'scores.*.*' => 'nullable|numeric|min:0|max:100',
            'absent' => 'array',
            'absent.*' => 'array',
        ]);

        // Type: the period's fixed type (Swallow Test 1/2/Exam), else what the teacher
        // chose in the form (public schools mark each month as a Test or an Exam).
        $typeName = $period['month']['type'] ?? ($request->input('type') === 'Exam' ? 'Exam' : 'Test');
        $type = $this->assessmentType($school, $typeName);
        $period['typeName'] = $typeName;
        $period['label'] = ($period['month']['type'] ?? null)
            ? ($period['month']['label'] ?? $typeName)
            : trim(($period['month']['label'] ?? '').' '.$typeName);
        $studentIds = $this->students($offering)->pluck('id')->all();
        $now = now()->toDateTimeString();

        // A subject teacher may only save their own subjects' columns.
        $editable = $this->editableSubjectIds($offering, $request->user());

        $absentFlags = $request->input('absent', []);
        foreach ($this->subjects($offering, $period['term']) as $subject) {
            if ($editable !== null && ! in_array($subject->id, $editable, true)) {
                continue;
            }
            $exam = $this->findOrCreateExam($offering, $subject->id, $period, $type);
            $column = $request->input("scores.{$subject->id}", []);
            $absentColumn = $absentFlags[$subject->id] ?? [];
            foreach ($studentIds as $sid) {
                $isAbsent = (int) ($absentColumn[$sid] ?? 0) === 1;
                $raw = $column[$sid] ?? null;
                if ($isAbsent) {
                    [$score, $absent] = [0, 1];
                } elseif ($raw !== null && $raw !== '') {
                    [$score, $absent] = [(int) round($raw), 0];
                } else {
                    continue; // blank and not marked absent: not entered yet, leave any existing record be
                }
                DB::table('assessment_scores')->updateOrInsert(
                    ['assessment_id' => $exam->id, 'user_id' => $sid],
                    ['score' => $score, 'absent' => $absent, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        return redirect()->route('term-grid.report', $this->periodParams($offering, $period))->with('success', 'Marks saved.');
    }

    // ---- PDFs ---------------------------------------------------------------

    public function resultSheet(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'], 404);

        $subjects = $this->subjects($offering, $period['term'])->filter(fn ($s) => $s->countsTowardTotalResolved())->values();
        $exams = $this->periodExams($offering, $period)->keyBy('subject_id');

        $pdf = PDF::loadView('print.term-result-sheet', [
            'offering' => $offering, 'school' => $school, 'term' => $period['term'],
            'periodTitle' => $period['label'],
            'teacher' => $offering->principal->first(),
            'subjects' => $subjects,
            'rows' => $this->rankedRows($offering, $exams, $subjects),
            'passMark' => 40,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Result sheet '.$offering->displayName().' '.$period['label'].'.pdf');
    }

    public function analysis(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'], 404);

        $subjects = $this->subjects($offering, $period['term'])->filter(fn ($s) => $s->countsTowardTotalResolved())->values();
        $exams = $this->periodExams($offering, $period)->keyBy('subject_id');

        $pdf = PDF::loadView('print.term-analysis', [
            'offering' => $offering, 'school' => $school, 'term' => $period['term'],
            'periodTitle' => $period['label'],
            'analysis' => $this->analysisData($offering, $exams, $subjects),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Analysis '.$offering->displayName().' '.$period['label'].'.pdf');
    }

    public function histogram(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'], 404);

        $subjects = $this->subjects($offering, $period['term'])->filter(fn ($s) => $s->countsTowardTotalResolved())->values();
        $exams = $this->periodExams($offering, $period)->keyBy('subject_id');

        $outline = $request->boolean('outline');
        $pdf = PDF::loadView('print.term-histogram', [
            'offering' => $offering, 'school' => $school, 'term' => $period['term'],
            'periodTitle' => $period['label'],
            'studentCount' => $this->students($offering)->count(),
            'analysis' => $this->analysisData($offering, $exams, $subjects),
            'outline' => $outline,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Histogram '.($outline ? 'blank ' : '').$offering->displayName().' '.$period['label'].'.pdf');
    }

    /** One click: result sheet + analysis + histogram in a single printable PDF. */
    public function bundle(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'], 404);

        $subjects = $this->subjects($offering, $period['term'])->filter(fn ($s) => $s->countsTowardTotalResolved())->values();
        $exams = $this->periodExams($offering, $period)->keyBy('subject_id');

        $pdf = PDF::loadView('print.term-bundle', [
            'offering' => $offering, 'school' => $school, 'term' => $period['term'],
            'periodTitle' => $period['label'],
            'teacher' => $offering->principal->first(),
            'subjects' => $subjects,
            'studentCount' => $this->students($offering)->count(),
            'rows' => $this->rankedRows($offering, $exams, $subjects),
            'analysis' => $this->analysisData($offering, $exams, $subjects),
            'outline' => $request->boolean('outline'),
            'passMark' => 40,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Results '.($request->boolean('outline') ? 'blank ' : '').$offering->displayName().' '.$period['label'].'.pdf');
    }

    /** The same result sheet / analysis / histogram, on screen with tabs. */
    public function report(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'], 404);

        $subjects = $this->subjects($offering, $period['term'])->filter(fn ($s) => $s->countsTowardTotalResolved())->values();
        $exams = $this->periodExams($offering, $period)->keyBy('subject_id');

        return view('pages.scorebook.term-report', [
            'offering' => $offering, 'school' => $school, 'period' => $period,
            'teacher' => $offering->principal->first(),
            'subjects' => $subjects,
            'studentCount' => $this->students($offering)->count(),
            'rows' => $this->rankedRows($offering, $exams, $subjects),
            'analysis' => $this->analysisData($offering, $exams, $subjects),
            'hasMarks' => $exams->isNotEmpty(),
            'passMark' => 40,
        ]);
    }

    /**
     * "By term" view: the whole term combined (Test 1 + Test 2 + Exam weighted into
     * each subject's term mark), in the same result-sheet layout as By subject / By test.
     */
    public function term(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $terms = $offering->schoolyear?->terms()->orderBy('start')->get() ?? collect();
        $term = $this->resolveTerm($terms, (int) $request->input('term') ?: 0) ?? $terms->last();

        $subjects = $term ? $this->subjects($offering, $term) : collect();

        $ranked = $term
            ? (new \App\Domain\Reporting\Services\PositionService)->rankedReports($offering, $term, $school)
            : collect();

        // Rank order (highest term total first); each row carries the per-subject
        // term mark + total/ave/pos, and the enrollment id for the per-pupil report link.
        $enrollmentByUser = $term ? $offering->enrollments()->pluck('id', 'user_id') : collect();
        $rows = $ranked->map(function ($r) use ($enrollmentByUser) {
            $marks = collect($r['report']['results']['subjectResults'] ?? [])
                ->map(fn ($sr) => ($sr['hasScores'] ?? false) ? $sr['subjectTotal'] : null);

            return [
                'student'       => $r['student'],
                'enrollment_id' => $enrollmentByUser[$r['student']->id] ?? null,
                'marks'         => $marks, // keyed by subject name
                'total'         => $r['total'],
                'average'       => $r['average'],
                'position'      => $r['position'],
            ];
        })->values();

        return view('pages.scorebook.term-overview', [
            'offering' => $offering, 'school' => $school, 'terms' => $terms,
            'term' => $term, 'subjects' => $subjects, 'rows' => $rows,
            'teacher' => $offering->principal->first(),
            'gradeNum' => (int) preg_replace('/\D/', '', $offering->grade->name ?? ''),
            'passMark' => 40,
        ]);
    }

    /** Demo reset: wipe this test's assessments and scores so it's empty again. */
    public function clearPeriod(Request $request, Offering $offering)
    {
        $offering->load('schoolyear');
        $period = $this->resolvePeriod($offering, $request);
        abort_unless($period['term'], 400);

        $examIds = $this->periodExams($offering, $period)->pluck('id');
        AssessmentScore::whereIn('assessment_id', $examIds)->delete();
        Assessment::whereIn('id', $examIds)->delete();

        return redirect()
            ->route('term-grid.report', $this->periodParams($offering, $period))
            ->with('success', 'Marks cleared.');
    }

    // ---- data ---------------------------------------------------------------

    /** One row per pupil: mark per subject (null = absent), total, average, position (ties shared). */
    private function rankedRows(Offering $offering, $exams, $subjects): array
    {
        $students = $this->students($offering);
        $scoreMap = $this->examScores($exams);

        $rows = [];
        foreach ($students as $st) {
            $marks = [];
            $total = 0.0;
            foreach ($subjects as $subj) {
                $exam = $exams[$subj->id] ?? null;
                $entry = $exam ? ($scoreMap[$exam->id][$st->id] ?? null) : null;
                if ($entry && ! $entry['absent']) {
                    $marks[$subj->id] = $entry['score'];
                    $total += $entry['score'];
                } else {
                    $marks[$subj->id] = null;
                }
            }
            $rows[] = [
                'student' => $st, 'marks' => $marks, 'total' => $total,
                'average' => $subjects->count() ? (int) round($total / $subjects->count()) : 0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);
        $position = 0;
        $prev = null;
        $seen = 0;
        foreach ($rows as &$r) {
            $seen++;
            if ($r['total'] !== $prev) {
                $position = $seen;
                $prev = $r['total'];
            }
            $r['position'] = $position;
            $r['positionLabel'] = $this->ordinal($position);
        }
        unset($r);

        return $rows;
    }

    /** Per-subject stats keyed male/female/overall (fail < 40, pass >= 40 incl. mastery, mastery >= 80). */
    private function analysisData(Offering $offering, $exams, $subjects)
    {
        $students = $this->students($offering);
        $genders = Profile::whereIn('user_id', $students->pluck('id'))->pluck('gender', 'user_id');
        $scoreMap = $this->examScores($exams);

        $statsFor = function ($subset, $exam) use ($scoreMap) {
            $marks = [];
            foreach ($subset as $st) {
                $entry = $exam ? ($scoreMap[$exam->id][$st->id] ?? null) : null;
                if ($entry && ! $entry['absent']) {
                    $marks[] = $entry['score'];
                }
            }
            $sat = count($marks);
            $count = fn ($fn) => count(array_filter($marks, $fn));
            $pct = fn ($n) => $sat ? (int) round($n / $sat * 100) : 0;
            $fail = $count(fn ($m) => $m < 40);
            $pass = $count(fn ($m) => $m >= 40);
            $mastery = $count(fn ($m) => $m >= 80);

            return [
                'students' => $subset->count(), 'sat' => $sat,
                'fail' => $fail, 'failPct' => $pct($fail),
                'pass' => $pass, 'passPct' => $pct($pass),
                'mastery' => $mastery, 'masteryPct' => $pct($mastery),
                'average' => $sat ? (int) round(array_sum($marks) / $sat) : 0,
            ];
        };

        $males = $students->filter(fn ($s) => ($genders[$s->id] ?? null) === 'M');
        $females = $students->filter(fn ($s) => ($genders[$s->id] ?? null) === 'F');

        return $subjects->map(fn ($subj) => [
            'subject' => $subj,
            'male' => $statsFor($males, $exams[$subj->id] ?? null),
            'female' => $statsFor($females, $exams[$subj->id] ?? null),
            'overall' => $statsFor($students, $exams[$subj->id] ?? null),
        ]);
    }

    private function examScores($exams): array
    {
        $map = [];
        foreach (AssessmentScore::whereIn('assessment_id', collect($exams)->pluck('id'))->get() as $s) {
            $map[$s->assessment_id][$s->user_id] = ['score' => (float) $s->score, 'absent' => (bool) $s->absent];
        }

        return $map;
    }

    // ---- period + lookups ---------------------------------------------------

    /**
     * The assessment period being viewed: a term, a month within it, and a type
     * (Test/Exam). Defaults to the term's last month as an Exam (the end-of-term).
     */
    private function resolvePeriod(Offering $offering, Request $request): array
    {
        $terms = $offering->schoolyear?->terms()->orderBy('start')->get() ?? collect();

        // Open on the period that already has marks, so real data shows and a PDF is
        // one click away; fall back to the current term's last month.
        $latest = Assessment::where('offering_id', $offering->id)->whereNotNull('date')->orderByDesc('date')->first();

        $term = $this->resolveTerm($terms, (int) $request->input('term') ?: (int) ($latest?->term_id ?? 0));
        $months = $term ? $this->monthsIn($term) : [];

        $monthParam = $request->input('month');
        if (! $monthParam && $latest && $term && (int) $latest->term_id === $term->id) {
            $monthParam = Carbon::parse($latest->date)->format('Y-m');
        }
        $month = collect($months)->firstWhere('value', $monthParam)
            ?: ($months ? $months[count($months) - 1] : null);

        // A month holds one test or one exam. Use the type already on this month's
        // marks; else what the teacher chose in the form; else default to Exam.
        $existingType = null;
        if ($term && $month) {
            $existing = Assessment::where('offering_id', $offering->id)->where('term_id', $term->id)
                ->whereYear('date', $month['y'])->whereMonth('date', $month['m'])->first();
            $existingType = $existing ? optional(AssessmentType::find($existing->assessment_type_id))->name : null;
        }
        // Type: existing marks win; else the period's fixed type (Swallow Test 1/2/Exam);
        // else what the teacher chose in the form (public schools pick Test/Exam per month).
        $typeName = $existingType
            ?? ($month['type'] ?? (in_array($request->input('type'), ['Test', 'Exam'], true) ? $request->input('type') : 'Test'));

        $date = $month ? sprintf('%04d-%02d-15', $month['y'], $month['m']) : null;
        // Months mode label reads "January Test"; tests mode is already "Test 1" / "Exam".
        $label = $month ? (($month['type'] ?? null) ? $month['label'] : trim($month['label'].' '.$typeName)) : $typeName;
        $mode = $this->periodMode();

        return compact('terms', 'term', 'months', 'month', 'typeName', 'date', 'label', 'existingType', 'mode');
    }

    /** GET params that round-trip the current period. */
    private function periodParams(Offering $offering, array $period): array
    {
        return [
            'offering' => $offering,
            'term' => $period['term']?->id,
            'month' => $period['month']['value'] ?? null,
        ];
    }

    /** How this school splits a term in the scorebook: 'months' (public schools, default) or 'tests' (Swallow). */
    private function periodMode(): string
    {
        return School::first()?->config(\App\Models\SchoolConfig::SCOREBOOK_PERIOD_MODE, 'months') === 'tests' ? 'tests' : 'months';
    }

    /**
     * The periods within a term. Public schools work per calendar month (each month
     * is a Test or an Exam the teacher chooses). The Swallow works per term: Test 1,
     * Test 2 and the Exam, each stored in its own month bucket (first, second, last
     * month) with a fixed type.
     */
    private function monthsIn(Term $term): array
    {
        $start = Carbon::parse($term->start)->startOfMonth();
        $end = Carbon::parse($term->end)->startOfMonth();

        if ($this->periodMode() === 'tests') {
            $test1 = $start->copy();
            $exam = $end->gt($start) ? $end->copy() : $start->copy()->addMonths(2);
            $test2 = $start->copy()->addMonth();
            if ($test2->gte($exam)) {
                $test2 = $exam->copy()->subMonth();
            }
            if ($test2->lte($test1)) {
                $test2 = $test1->copy()->addMonth();
            }
            $defs = [['Test 1', 'Test', $test1], ['Test 2', 'Test', $test2], ['Exam', 'Exam', $exam]];

            return array_map(fn ($d) => [
                'value' => $d[2]->format('Y-m'), 'label' => $d[0], 'type' => $d[1],
                'y' => (int) $d[2]->format('Y'), 'm' => (int) $d[2]->format('n'),
            ], $defs);
        }

        // Public schools: one period per calendar month; type is chosen per month.
        $d = $start->copy();
        $months = [];
        $guard = 0;
        while ($d <= $end && $guard++ < 24) {
            $months[] = ['value' => $d->format('Y-m'), 'label' => $d->format('F'), 'type' => null, 'y' => (int) $d->format('Y'), 'm' => (int) $d->format('n')];
            $d = $d->copy()->addMonth();
        }

        return $months;
    }

    /** The one-per-subject assessments making up a month (whichever type they are). */
    private function periodExams(Offering $offering, array $period)
    {
        if (! $period['term'] || ! $period['month']) {
            return collect();
        }

        return Assessment::where('offering_id', $offering->id)
            ->where('term_id', $period['term']->id)
            ->whereYear('date', $period['month']['y'])
            ->whereMonth('date', $period['month']['m'])
            ->get();
    }

    private function findOrCreateExam(Offering $offering, int $subjectId, array $period, AssessmentType $type): Assessment
    {
        $exam = Assessment::where('offering_id', $offering->id)
            ->where('subject_id', $subjectId)
            ->where('term_id', $period['term']->id)
            ->whereYear('date', $period['month']['y'])
            ->whereMonth('date', $period['month']['m'])
            ->first();

        if ($exam) {
            // The teacher may have (re)chosen the type on this save; keep it in sync.
            if ($exam->assessment_type_id !== $type->id) {
                $exam->update(['assessment_type_id' => $type->id, 'name' => $period['label']]);
            }

            return $exam;
        }

        return Assessment::create([
            'offering_id' => $offering->id,
            'subject_id' => $subjectId,
            'term_id' => $period['term']->id,
            'assessment_type_id' => $type->id,
            'name' => $period['label'],
            'date' => $period['date'],
            'max_score' => 100,
            'confirmed' => 1,
        ]);
    }

    private function assessmentType(?School $school, string $name, bool $create = true): ?AssessmentType
    {
        if (! $create) {
            return AssessmentType::where('school_id', $school?->id)->where('name', $name)->first();
        }

        return AssessmentType::firstOrCreate(
            ['school_id' => $school->id, 'name' => $name],
            ['weight' => $name === 'Exam' ? 0.75 : 0.25, 'display_order' => $name === 'Exam' ? 2 : 1],
        );
    }

    private function ordinal(int $n): string
    {
        $mod = $n % 100;
        if ($mod >= 11 && $mod <= 13) {
            return $n.'th';
        }

        return $n.(['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
    }

    private function resolveTerm($terms, int $requested): ?Term
    {
        if ($requested && ($t = $terms->firstWhere('id', $requested))) {
            return $t;
        }
        $now = now();

        return $terms->first(fn ($t) => $t->start <= $now && $t->end >= $now) ?? $terms->first();
    }

    private function students(Offering $offering)
    {
        return $offering->enrollments()
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->orderBy('users.last_name')->orderBy('users.first_name')
            ->with('student')->select('enrollments.*')->get()
            ->map(fn ($e) => $e->student)->filter()->values();
    }

    private function subjects(Offering $offering, Term $term)
    {
        // The school's own subject order (creation order = curriculum order), counting subjects first.
        return $offering->subjects($term->id)->get()
            ->sortBy('id')
            ->sortBy(fn ($s) => $s->countsTowardTotalResolved() ? 0 : 1)
            ->values();
    }
}
