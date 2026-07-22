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

        // The same rules save() enforces, applied before the teacher starts typing.
        if ($period['term'] && $period['term']->isLocked() && ! $request->user()->hasAnyRole(['headmaster', 'admin'])) {
            return redirect()->route('term-grid.report', $this->periodParams($offering, $period))
                ->with('error', "Term \"{$period['term']->name}\" is closed. Ask the headmaster to enter or correct scores.");
        }
        $this->assertClassAccess($offering, $request->user());

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

        // Letter-only ("graded") subjects are filled in by hand on the printed card,
        // so their columns only clutter the grid. Hide them unless asked for — but a
        // graded column that already has marks this period always stays visible, so
        // entered data can never be out of sight.
        $showGraded = $request->boolean('graded');
        $gradedHidden = 0;
        if (! $showGraded) {
            $before = $subjects->count();
            $subjects = $subjects->filter(fn ($s) => $s->countsTowardTotalResolved() || isset($existing[$s->id]))->values();
            $gradedHidden = $before - $subjects->count();
        }

        // Per column: the existing assessment's max (marks must fit it) and, when it
        // already carries scores, its type — so the grid can label a column that is
        // pinned to e.g. Exam and save() will refuse to write it as Test.
        $columnMeta = [];
        foreach ($exams as $exam) {
            $columnMeta[$exam->subject_id] = [
                'max' => (int) $exam->max_score,
                'type' => $exam->assessmentType->name ?? null,
                'locked_type' => $exam->scores()->exists(),
            ];
        }

        $editableSubjects = $this->editableSubjectIds($offering, $request->user());

        // Each pupil's running TERM total (the report figure), shown next to the
        // name while entering so the teacher sees the effect of saved marks.
        $termTotals = $period['term']
            ? (new \App\Domain\Reporting\Services\PositionService)->rank($offering, $period['term'], $school)->keyBy('student_id')
            : collect();

        return view('pages.scorebook.term-grid', compact('offering', 'school', 'students', 'subjects', 'existing', 'period', 'editableSubjects', 'columnMeta', 'showGraded', 'gradedHidden', 'termTotals'));
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

        // Same rules as the wizard: no writes into a closed term (headmaster/admin
        // may still correct), and a teacher must actually belong to this class.
        $this->assertTermWritable($period['term'], $request->user());
        $this->assertClassAccess($offering, $request->user());

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
        $skipped = [];
        foreach ($this->subjects($offering, $period['term']) as $subject) {
            if ($editable !== null && ! in_array($subject->id, $editable, true)) {
                continue;
            }

            // A column with marks under a DIFFERENT type (e.g. an exam entered via the
            // wizard) must never be silently retyped or written into — that is exactly
            // how an exam ends up weighing 0.25. Skip the column and tell the teacher.
            $existing = $this->findExam($offering, $subject->id, $period);
            if ($existing && $existing->assessment_type_id !== $type->id && $existing->scores()->exists()) {
                $skipped[] = "{$subject->name} (already saved as {$existing->assessmentType->name})";
                continue;
            }

            $column = $request->input("scores.{$subject->id}", []);
            $absentColumn = $absentFlags[$subject->id] ?? [];

            // An untouched column (another teacher's subject, left blank) must leave
            // no trace: creating an empty assessment for it would litter the term
            // and skew the stray-assessment detection.
            $hasAny = collect($column)->contains(fn ($v) => $v !== null && $v !== '')
                || collect($absentColumn)->contains(fn ($v) => (int) $v === 1);
            if (! $hasAny) {
                continue;
            }

            // Marks must fit the column's own maximum (a wizard-made test can be out
            // of 25, not 100). Reject the whole column rather than record 80/25.
            $max = (int) ($existing->max_score ?? 100);
            $over = collect($column)->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > $max);
            if ($over->isNotEmpty()) {
                $skipped[] = "{$subject->name} (marks above its maximum of {$max})";
                continue;
            }

            $exam = $existing ?? $this->findOrCreateExam($offering, $subject->id, $period, $type);
            if ($existing && $existing->assessment_type_id !== $type->id) {
                // No scores yet: honouring the (re)chosen type is safe.
                $existing->update(['assessment_type_id' => $type->id, 'name' => $period['label']]);
            }
            foreach ($studentIds as $sid) {
                $isAbsent = (int) ($absentColumn[$sid] ?? 0) === 1;
                $raw = $column[$sid] ?? null;
                if ($isAbsent) {
                    // Absent stores a null score (the model's invariant); reports read it as 0%.
                    [$score, $absent] = [null, 1];
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

        $redirect = redirect()->route('term-grid.report', $this->periodParams($offering, $period));

        return $skipped
            ? $redirect->with('error', 'Not saved for: '.implode('; ', $skipped).'. The other columns were saved.')
            : $redirect->with('success', 'Marks saved.');
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
            'analysis' => $this->analysisData($offering, $exams, $this->analysisSubjects($subjects)),
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
            'satCount' => $this->satCount($exams),
            'analysis' => $this->analysisData($offering, $exams, $this->analysisSubjects($subjects)),
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
            'satCount' => $this->satCount($exams),
            'rows' => $this->rankedRows($offering, $exams, $subjects),
            'analysis' => $this->analysisData($offering, $exams, $this->analysisSubjects($subjects)),
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
            'analysis' => $this->analysisData($offering, $exams, $this->analysisSubjects($subjects)),
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

    /**
     * Subjects the result analysis and histogram cover: the class's flagged core
     * subjects when any are set (a school's promotion analysis runs on core
     * subjects only), else every counting subject — so schools without flags
     * keep the full analysis.
     */
    private function analysisSubjects($countingSubjects)
    {
        $core = $countingSubjects->filter(fn ($s) => (int) ($s->pivot->core ?? 0) === 1)->values();

        return $core->isNotEmpty() ? $core : $countingSubjects;
    }

    /** Pupils who actually sat this period: at least one subject with a real (non-absent) mark. */
    private function satCount($exams): int
    {
        return AssessmentScore::whereIn('assessment_id', $exams->pluck('id'))
            ->where('absent', 0)->whereNotNull('score')
            ->distinct()->count('user_id');
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
            // The three bands are exclusive so fail + pass + mastery totals 100%.
            $fail = $count(fn ($m) => $m < 40);
            $pass = $count(fn ($m) => $m >= 40 && $m < 80);
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

        $latest = Assessment::where('offering_id', $offering->id)->whereNotNull('date')->orderByDesc('date')->first();

        // Term precedence: the URL's choice, else the term we are IN today (at entry
        // time a fresh current term must not lose to an old term that has marks),
        // else the term holding the most recent marks (between years), else the first.
        $requested = (int) $request->input('term');
        $now = now();
        $term = ($requested ? $terms->firstWhere('id', $requested) : null)
            ?? $terms->first(fn ($t) => $t->start <= $now && $t->end >= $now)
            ?? ($latest ? $terms->firstWhere('id', (int) $latest->term_id) : null)
            ?? $terms->first();
        $months = $term ? $this->monthsIn($term, $offering) : [];

        $monthParam = $request->input('month');
        if (! $monthParam && $latest && $term && (int) $latest->term_id === $term->id) {
            $monthParam = Carbon::parse($latest->date)->format('Y-m');
        }
        // No explicit period and nothing entered yet in this term: start at the
        // FIRST bucket (Test 1), not the last — a fresh term opening on "Exam"
        // invites the first marks to land in the wrong column.
        $month = collect($months)->firstWhere('value', $monthParam)
            ?: ($months ? ($monthParam ? $months[count($months) - 1] : $months[0]) : null);

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
    private function monthsIn(Term $term, ?Offering $offering = null): array
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

            $months = array_map(fn ($d) => [
                'value' => $d[2]->format('Y-m'), 'label' => $d[0], 'type' => $d[1],
                'y' => (int) $d[2]->format('Y'), 'm' => (int) $d[2]->format('n'),
            ], $defs);

            // Marks can live outside the three canonical buckets — entered before the
            // school switched to tests mode, or seeded month-style. Those months get
            // their own bucket (named by month, typed from their data) so the marks
            // stay reachable and a shared ?month= URL always resolves to what it names.
            if ($offering) {
                $known = array_column($months, 'value');
                $extra = Assessment::where('offering_id', $offering->id)
                    ->where('term_id', $term->id)
                    ->whereNotNull('date')
                    ->with('assessmentType')
                    ->get()
                    ->groupBy(fn ($a) => Carbon::parse($a->date)->format('Y-m'))
                    ->reject(fn ($g, $ym) => in_array($ym, $known, true));

                foreach ($extra as $ym => $group) {
                    $d = Carbon::parse($ym.'-01');
                    $months[] = [
                        'value' => $ym,
                        'label' => $d->format('F'),
                        'type' => $group->contains(fn ($a) => ($a->assessmentType->name ?? '') === 'Exam') ? 'Exam' : 'Test',
                        'y' => (int) $d->format('Y'), 'm' => (int) $d->format('n'),
                    ];
                }
                usort($months, fn ($a, $b) => [$a['y'], $a['m']] <=> [$b['y'], $b['m']]);
            }

            return $months;
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

    /** The period's existing assessment for a subject (the grid's month bucket), if any. */
    private function findExam(Offering $offering, int $subjectId, array $period): ?Assessment
    {
        return Assessment::where('offering_id', $offering->id)
            ->where('subject_id', $subjectId)
            ->where('term_id', $period['term']->id)
            ->whereYear('date', $period['month']['y'])
            ->whereMonth('date', $period['month']['m'])
            ->first();
    }

    private function findOrCreateExam(Offering $offering, int $subjectId, array $period, AssessmentType $type): Assessment
    {
        // Retyping an existing assessment is save()'s decision (it refuses once
        // scores exist); here we only find or create.
        return $this->findExam($offering, $subjectId, $period) ?? Assessment::create([
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

    /** The wizard's term-lock rule, applied to the grid: no writes into a closed term. */
    private function assertTermWritable(?Term $term, $user): void
    {
        if (! $term || ! $term->isLocked()) {
            return;
        }
        if ($user?->hasAnyRole(['headmaster', 'admin'])) {
            return;
        }
        abort(403, "Term \"{$term->name}\" is closed. Ask the headmaster to enter or correct scores.");
    }

    /**
     * A teacher may only write marks for a class they are attached to — as class
     * teacher (teacher_offering) or as subject teacher (teacher_assignments).
     * Oversight roles pass; anyone can still READ.
     */
    private function assertClassAccess(Offering $offering, $user): void
    {
        if ($user?->hasAnyRole(['headmaster', 'admin', 'assistant_coordinator'])) {
            return;
        }
        $attached = DB::table('teacher_offering')->where('offering_id', $offering->id)->where('user_id', $user?->id)->exists()
            || DB::table('teacher_assignments')->where('offering_id', $offering->id)->where('user_id', $user?->id)->exists();
        abort_unless($attached, 403, 'You are not assigned to this class.');
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
        // Counting subjects first, then the school's own order: an explicit
        // per-class sort_order when set (matching their paper sheets), else the
        // historic subject-id order.
        return $offering->subjects($term->id)->get()
            ->sortBy(fn ($s) => [
                $s->countsTowardTotalResolved() ? 0 : 1,
                $s->pivot->sort_order !== null ? (int) $s->pivot->sort_order : PHP_INT_MAX,
                $s->id,
            ])
            ->values();
    }
}
