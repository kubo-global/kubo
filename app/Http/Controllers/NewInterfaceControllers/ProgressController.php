<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Support\SchoolCalendar;
use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\LessonAssignment;
use App\Domain\Learning\Models\Skill;
use App\Domain\Learning\Models\StudentSkill;

class ProgressController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $schoolyear = Schoolyear::orderByDesc('id')->first();

        if (!$schoolyear) {
            return view('pages.progress.index', ['offerings' => collect()]);
        }

        $offerings = Teacher::find($user->id)
            ?->offerings()
            ->where('schoolyear_id', $schoolyear->id)
            ->with('grade')
            ->get() ?? collect();

        return view('pages.progress.index', [
            'offerings' => $offerings,
        ]);
    }

    public function show(Offering $offering)
    {
        $user = auth()->user();
        $currentWeek = SchoolCalendar::currentSchoolWeek();

        $students = $offering->students()
            ->orderBy('first_name')
            ->get();

        $gradeId = $offering->grade_id;

        // All skills for this grade that have practice content
        $skills = Skill::where('grade_id', $gradeId)
            ->whereHas('content', fn ($q) => $q->where('role', 'practice'))
            ->orderBy('level')
            ->get();

        $studentIds = $students->pluck('id')->toArray();
        $skillIds = $skills->pluck('id')->toArray();

        // Bulk load all student skill records
        $studentSkills = StudentSkill::whereIn('user_id', $studentIds)
            ->whereIn('skill_id', $skillIds)
            ->get()
            ->groupBy('user_id');

        // Recent exercise runs (last 7 days)
        $recentRuns = ExerciseRun::whereIn('user_id', $studentIds)
            ->whereIn('skill_id', $skillIds)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy('user_id');

        // This week's assignment
        $assignment = LessonAssignment::forOffering($offering->id)
            ->forWeek($currentWeek)
            ->with('skill')
            ->first();

        // Build per-student summary
        $studentProgress = $students->map(function ($student) use ($studentSkills, $recentRuns, $skills) {
            $ss = $studentSkills->get($student->id, collect());

            $mastered = $ss->where('status', 'mastered')->count();
            $struggling = $ss->where('status', 'struggling')->count();
            $inProgress = $ss->where('status', 'in_progress')->count();
            $runs = $recentRuns->get($student->id, collect());

            return [
                'student' => $student,
                'mastered' => $mastered,
                'struggling' => $struggling,
                'in_progress' => $inProgress,
                'total_skills' => $skills->count(),
                'runs_this_week' => $runs->count(),
                'avg_score' => $runs->count() > 0 ? round($runs->avg('score')) : null,
                'skills' => $ss->keyBy('skill_id'),
            ];
        });

        return view('pages.progress.show', [
            'offering' => $offering,
            'students' => $studentProgress,
            'skills' => $skills,
            'assignment' => $assignment,
            'currentWeek' => $currentWeek,
        ]);
    }
}
