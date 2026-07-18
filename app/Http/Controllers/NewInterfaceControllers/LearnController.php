<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Support\SchoolCalendar;
use Illuminate\Http\Request;
use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\Skill;
use App\Domain\Learning\Models\StudentSkill;
use KuboKolibri\Services\ExerciseRunService;
use KuboKolibri\Services\KolibriSessionBridge;
use App\Domain\Learning\SkillGraph;

class LearnController extends Controller
{
    public function index(SkillGraph $graph)
    {
        $user = auth()->user();

        // Find the first subject that has skills with practice content for this student's grade
        $gradeId = $user->currentGradeId();
        $subject = $gradeId
            ? Subject::whereHas('skills', fn ($q) => $q->where('grade_id', $gradeId)
                ->whereHas('content', fn ($q2) => $q2->where('role', 'practice')->where('approved', true)))
                ->first()
            : null;

        if (!$subject) {
            return view('pages.learn.index', [
                'diagnosis' => null,
                'frontier' => collect(),
                'nextSkill' => null,
                'assignedSkill' => null,
                'reviewSkill' => null,
            ]);
        }

        $recommendations = $graph->recommendationsForStudent(
            $user->id,
            $subject->id,
            $gradeId,
            $user->currentOfferingId(),
            SchoolCalendar::currentSchoolWeek(),
        );

        $recommendations = $this->applyTopicGate($recommendations, $user->currentOfferingId());
        $recommendations = $this->applyTopicRotation($recommendations, $user->currentOfferingId(), $gradeId, $subject->id);

        return view('pages.learn.index', array_merge(
            ['subject' => $subject],
            $recommendations,
        ));
    }

    /**
     * Bias the Practice button toward what the class is currently learning.
     *
     * Each /learn load picks a covered topic via weighted-random — more
     * recently-taught topics get higher weight — and overrides nextSkill
     * with a practice skill from that topic. Review skills are left alone
     * since spaced-repetition is time-sensitive.
     *
     * No-op when the offering has no lesson plans linked to curriculum
     * topics, or when no skill exists for the picked topic at the
     * student's grade.
     */
    private function applyTopicRotation(array $recommendations, ?int $offeringId, ?int $gradeId, int $subjectId): array
    {
        if (!$offeringId || !$gradeId) {
            return $recommendations;
        }

        $recentTopics = LessonPlan::where('offering_id', $offeringId)
            ->where('subject_id', $subjectId)
            ->whereNotNull('curriculum_topic_id')
            ->whereDate('lesson_date', '<=', now())
            ->orderByDesc('lesson_date')
            ->pluck('curriculum_topic_id')
            ->unique()
            ->values();

        if ($recentTopics->isEmpty()) {
            return $recommendations;
        }

        $pickedTopic = $this->pickWeightedByRecency($recentTopics->all());

        $skill = Skill::where('grade_id', $gradeId)
            ->where('subject_id', $subjectId)
            ->whereHas('content', fn ($q) => $q
                ->where('skill_content.role', 'practice')
                ->where('skill_content.approved', true)
                ->where('curriculum_maps.topic_id', $pickedTopic))
            ->inRandomOrder()
            ->first();

        if ($skill) {
            $recommendations['nextSkill'] = $skill;
        }

        return $recommendations;
    }

    /**
     * Weighted pick with linear decay — index 0 (most recent) gets weight N,
     * index N-1 gets weight 1. Gives "current topics" priority without
     * starving older ones.
     */
    private function pickWeightedByRecency(array $items): mixed
    {
        $n = count($items);
        $total = (int) ($n * ($n + 1) / 2);
        $r = mt_rand(1, max(1, $total));
        $cum = 0;
        foreach ($items as $i => $item) {
            $cum += $n - $i;
            if ($r <= $cum) {
                return $item;
            }
        }
        return $items[0];
    }

    /**
     * Filter the /learn recommendations so a skill only stays visible if it
     * has at least one approved practice exercise tagged to a curriculum
     * topic that's been covered in a lesson plan for the student's class.
     *
     * No-op when the school-wide `gate_exercises_by_lesson_plan` config is
     * off, or when the student has no current offering.
     */
    private function applyTopicGate(array $recommendations, ?int $offeringId): array
    {
        $gateOn = (bool) (School::first()?->config(\App\Models\SchoolConfig::GATE_EXERCISES_BY_LESSON_PLAN, false));
        if (!$gateOn || !$offeringId) {
            return $recommendations;
        }

        $offering = Offering::find($offeringId);
        $covered = $offering ? $offering->coveredTopicIds() : collect();

        $coveredSkillIds = $covered->isEmpty()
            ? []
            : Skill::withCoveredTopic($covered)->pluck('id')->all();

        $isCovered = fn ($skill) => $skill && in_array($skill->id, $coveredSkillIds, true);

        foreach (['assignedSkill', 'nextSkill', 'reviewSkill'] as $key) {
            if (isset($recommendations[$key]) && !$isCovered($recommendations[$key])) {
                $recommendations[$key] = null;
            }
        }

        if (isset($recommendations['allSkills'])) {
            $recommendations['allSkills'] = collect($recommendations['allSkills'])->filter($isCovered)->values();
        }

        if (isset($recommendations['previousGradeSkills'])) {
            $recommendations['previousGradeSkills'] = collect($recommendations['previousGradeSkills'])
                ->map(fn ($skills) => collect($skills)->filter($isCovered)->values())
                ->filter(fn ($skills) => $skills->isNotEmpty());
        }

        if (isset($recommendations['frontier'])) {
            $recommendations['frontier'] = collect($recommendations['frontier'])->filter($isCovered)->values();
        }

        return $recommendations;
    }

    public function skill(Skill $skill)
    {
        $user = auth()->user();
        $studentSkill = StudentSkill::where('user_id', $user->id)
            ->where('skill_id', $skill->id)
            ->first();

        $runs = ExerciseRun::forStudent($user->id)
            ->forSkill($skill->id)
            ->completed()
            ->where('total_questions', '>', 0)
            ->latest('completed_at')
            ->get();

        $hasExercise = (bool) $skill->practiceContent()->first();

        $offeringId = $user->currentOfferingId();
        $currentWeek = SchoolCalendar::currentSchoolWeek();
        $isAssigned = false;
        if ($offeringId) {
            $isAssigned = \KuboKolibri\Models\LessonAssignment::forOffering($offeringId)
                ->forWeek($currentWeek)
                ->where('skill_id', $skill->id)
                ->exists();
        }

        $isReviewDue = $studentSkill
            ? $studentSkill->isReviewDue($currentWeek)
            : false;

        $isStruggling = $studentSkill
            ? $studentSkill->isStruggling()
            : false;

        return view('pages.learn.skill', [
            'skill' => $skill,
            'studentSkill' => $studentSkill,
            'runs' => $runs,
            'hasExercise' => $hasExercise,
            'isAssigned' => $isAssigned,
            'isReviewDue' => $isReviewDue,
            'isStruggling' => $isStruggling,
        ]);
    }

    public function startRun(Request $request, Skill $skill, ExerciseRunService $runService)
    {
        $user = auth()->user();

        if (!$skill->practiceContent()->first()) {
            return back()->with('error', 'No exercise available for this skill.');
        }

        $run = $runService->startRun(
            $user,
            $skill,
            $request->input('mode', 'free'),
            $user->currentOfferingId(),
        );

        return redirect()->route('learn.exercise', ['skill' => $skill, 'run' => $run->id]);
    }

    public function exercise(Skill $skill, Request $request, KolibriSessionBridge $bridge, ExerciseRunService $runService)
    {
        $user = auth()->user();

        $run = ExerciseRun::where('id', $request->query('run'))
            ->where('user_id', $user->id)
            ->active()
            ->first();

        if (!$run) {
            return redirect()->route('learn.skill', $skill);
        }

        $exerciseMap = $skill->practiceContent()->first();
        if (!$exerciseMap) {
            return back()->with('error', 'No exercise available for this skill.');
        }

        try {
            $sessionData = $bridge->exerciseSessionData($user, $exerciseMap->kolibri_node_id);
        } catch (\Throwable $e) {
            return back()->with('error', 'The exercise server is not available right now. Please try again in a minute.');
        }

        $nextSkill = $runService->nextSkillInSequence($skill);

        $isReattempt = StudentSkill::where('user_id', $user->id)
            ->where('skill_id', $skill->id)
            ->where('status', 'mastered')
            ->exists();

        $response = response()->view('pages.learn.exercise', [
            'skill' => $skill,
            'run' => $run,
            'mode' => $run->mode,
            'isReattempt' => $isReattempt,
            'nodeId' => $exerciseMap->kolibri_node_id,
            'contentUrl' => $sessionData['contentUrl'],
            'nextSkill' => $nextSkill,
            'ssoReady' => $sessionData['ssoReady'],
        ]);

        // KUBO logged the learner into Kolibri server-side; hand the browser only
        // the resulting session cookie (raw header, scoped to the proxy path) so
        // the password never leaves the server. The proxy forwards it to Kolibri.
        foreach ($sessionData['cookieHeaders'] as $cookieHeader) {
            $response->headers->set('Set-Cookie', $cookieHeader, false);
        }

        return $response;
    }

    public function completeRun(Request $request, Skill $skill, ExerciseRunService $runService)
    {
        $validated = $request->validate([
            'run_id' => 'required|integer',
        ]);

        $run = ExerciseRun::where('id', $validated['run_id'])
            ->where('user_id', auth()->id())
            ->active()
            ->firstOrFail();

        $runService->completeRun($run, auth()->user(), $skill);

        if ($run->status === 'abandoned') {
            return redirect()->route('learn.skill', $skill);
        }

        if ($run->status === 'score_pending') {
            return redirect()->route('learn.complete', ['skill' => $skill, 'run' => $run->id, 'pending' => 1]);
        }

        return redirect()->route('learn.complete', ['skill' => $skill, 'run' => $run->id]);
    }

    public function complete(Skill $skill, Request $request, ExerciseRunService $runService)
    {
        $user = auth()->user();
        $run = null;

        if ($request->query('run')) {
            $run = ExerciseRun::where('id', $request->query('run'))
                ->where('user_id', $user->id)
                ->first();
        }

        $nextSkill = $runService->nextSkillInSequence($skill);

        $studentSkill = StudentSkill::where('user_id', $user->id)
            ->where('skill_id', $skill->id)
            ->first();

        return view('pages.learn.complete', [
            'skill' => $skill,
            'run' => $run,
            'mode' => $run?->mode ?? 'free',
            'nextSkill' => $nextSkill,
            'studentSkill' => $studentSkill,
        ]);
    }

    /**
     * Return skill graph data as JSON for the student detail view.
     */
    public function skillGraph(Student $student)
    {
        // Only teachers/admins can view student skill graphs
        if (!auth()->user()->hasAnyRole(['teacher', 'headmaster', 'admin'])) {
            abort(403);
        }

        $gradeId = $student->currentGradeId();

        $grades = \App\Models\Grade::where('id', '<=', $gradeId)
            ->orderBy('id')
            ->pluck('name', 'id');

        $skills = Skill::whereIn('grade_id', $grades->keys())
            ->with('prerequisites')
            ->orderBy('grade_id')
            ->orderBy('level')
            ->get();

        $studentSkills = StudentSkill::forStudent($student->id)
            ->whereIn('skill_id', $skills->pluck('id'))
            ->get()
            ->keyBy('skill_id');

        $gradeOrder = $grades->keys()->flip();
        $skillIdSet = $skills->pluck('id')->flip();

        $nodes = $skills->map(function (Skill $skill) use ($studentSkills, $gradeOrder, $grades) {
            $ss = $studentSkills->get($skill->id);
            return [
                'id' => $skill->id,
                'name' => $skill->name,
                'level' => $skill->level,
                'grade_index' => $gradeOrder[$skill->grade_id] ?? 0,
                'grade_name' => $grades[$skill->grade_id] ?? '',
                'scheduled_week' => $skill->scheduled_week,
                'status' => $ss ? $ss->status : 'not_started',
                'mastery' => $ss ? (float) $ss->mastery : 0,
                'attempts' => $ss ? $ss->attempts : 0,
            ];
        });

        $links = [];
        foreach ($skills as $skill) {
            foreach ($skill->prerequisites as $prereq) {
                if ($skillIdSet->has($prereq->id)) {
                    $links[] = [
                        'source' => $prereq->id,
                        'target' => $skill->id,
                    ];
                }
            }
        }

        return response()->json([
            'nodes' => $nodes->values(),
            'links' => $links,
        ]);
    }
}
