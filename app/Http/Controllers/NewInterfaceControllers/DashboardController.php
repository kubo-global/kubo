<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolCalendar;
use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\LessonAssignment;
use App\Domain\Learning\Models\StudentSkill;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Students get a single Learn entry point (handled in the view).
        if ($user->hasRole('student') && !$user->hasAnyRole(['teacher', 'headmaster', 'admin'])) {
            return view('pages.dashboard', ['year' => null, 'term' => null, 'overview' => null, 'insights' => null]);
        }

        $year = $this->currentSchoolyear();
        $term = $year ? $this->currentTerm($year) : null;

        $overview = $year ? $this->schoolOverview($user, $year, $term) : null;

        $insights = null;
        if ($year && $user->hasAnyRole(['teacher', 'headmaster', 'admin'])) {
            $insights = $this->teacherInsights($user, $year);
        }

        // Only health staff (the `view medical records` permission) see the follow-up nudge.
        // One number, matching the desk's "Needs follow-up": open wound cases + open incidents.
        $openFollowUps = $user->can('view medical records')
            ? \App\Models\WoundCase::whereNull('closed_on')->count()
                + \App\Models\IncidentReport::whereNull('closed_on')->count()
            : null;

        // Surface the once-a-year rollover only near year-end (current year is the
        // latest and within ~6 weeks of its end) — instead of a permanent nav item.
        $rolloverDue = false;
        if ($year && $year->end && $user->can('manage rollover')) {
            $isLatest = !Schoolyear::where('start', '>', $year->start)->exists();
            $rolloverDue = $isLatest && now()->gte(\Illuminate\Support\Carbon::parse($year->end)->subWeeks(6));
        }

        return view('pages.dashboard', compact('year', 'term', 'overview', 'insights', 'openFollowUps', 'rolloverDue'));
    }

    /** The school year we are currently in (now within its dates), else the most recent. */
    private function currentSchoolyear(): ?Schoolyear
    {
        $now = now();

        return Schoolyear::where('start', '<=', $now)->where('end', '>=', $now)->first()
            ?? Schoolyear::orderByDesc('start')->first();
    }

    /** The term we are currently in within the given year, if any. */
    private function currentTerm(Schoolyear $year): ?Term
    {
        $now = now();

        return $year->terms()->where('start', '<=', $now)->where('end', '>=', $now)->first();
    }

    /**
     * Headline numbers for the dashboard. Headmaster/admin see the whole school;
     * a teacher sees only the classes they are attached to.
     */
    private function schoolOverview(User $user, Schoolyear $year, ?Term $term): array
    {
        $isAdmin = $user->hasAnyRole(['headmaster', 'admin']);

        $offeringsQuery = Offering::where('schoolyear_id', $year->id);

        if (!$isAdmin) {
            $offeringsQuery->whereIn('id', DB::table('teacher_offering')->where('user_id', $user->id)->pluck('offering_id'));
        }

        $offerings = $offeringsQuery->with('grade')
            ->withCount(['enrollments as students_count' => fn ($q) => $q->whereHas('student', fn ($u) => $u->where('archived', false))])
            ->orderBy('grade_id')
            ->get();

        // Tests/exams entered for each class in the current term (term_id is null for
        // the NAT, so filtering by the term naturally excludes it).
        $termAssessments = $term
            ? DB::table('assessments')
                ->whereIn('offering_id', $offerings->pluck('id'))
                ->where('term_id', $term->id)
                ->groupBy('offering_id')
                ->selectRaw('offering_id, count(*) as c')
                ->pluck('c', 'offering_id')
            : collect();

        foreach ($offerings as $offering) {
            $offering->term_assessments = (int) ($termAssessments[$offering->id] ?? 0);
        }

        $students = DB::table('enrollments')
            ->whereIn('offering_id', $offerings->pluck('id'))
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->distinct('enrollments.user_id')
            ->count('enrollments.user_id');

        return [
            'isAdmin' => $isAdmin,
            'offerings' => $offerings,
            'students' => $students,
            'classes' => $offerings->count(),
            'teachers' => $isAdmin ? User::role('teacher')->where('archived', false)->count() : null,
            'subjects' => $isAdmin ? Subject::count() : null,
            'termAssessments' => $offerings->sum('term_assessments'),
        ];
    }

    private function teacherInsights($user, Schoolyear $schoolyear): array
    {
        // Get teacher's offerings
        $teacherOfferingIds = DB::table('teacher_offering')
            ->where('user_id', $user->id)
            ->pluck('offering_id');

        $offerings = Offering::whereIn('id', $teacherOfferingIds)
            ->where('schoolyear_id', $schoolyear->id)
            ->with('grade')
            ->get();

        if ($offerings->isEmpty()) {
            return ['offerings' => collect(), 'totalStudents' => 0, 'activeThisWeek' => 0, 'struggling' => 0, 'homeworkPending' => 0];
        }

        $studentIds = DB::table('enrollments')
            ->whereIn('offering_id', $offerings->pluck('id'))
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->pluck('enrollments.user_id')
            ->unique()
            ->toArray();

        // Active this week (students who completed an exercise in the last 7 days)
        $activeThisWeek = ExerciseRun::whereIn('user_id', $studentIds)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->distinct('user_id')
            ->count('user_id');

        // Struggling students
        $struggling = StudentSkill::whereIn('user_id', $studentIds)
            ->where('status', 'struggling')
            ->distinct('user_id')
            ->count('user_id');

        // Homework: students who haven't completed this week's assignment
        $currentWeek = SchoolCalendar::currentSchoolWeek();
        $homeworkPending = 0;

        foreach ($offerings as $offering) {
            $assignment = LessonAssignment::forOffering($offering->id)
                ->forWeek($currentWeek)
                ->first();

            if (!$assignment) continue;

            $offeringStudentIds = DB::table('enrollments')
                ->where('offering_id', $offering->id)
                ->pluck('user_id')
                ->toArray();

            $completedIds = ExerciseRun::whereIn('user_id', $offeringStudentIds)
                ->where('skill_id', $assignment->skill_id)
                ->where('mode', 'homework')
                ->where('status', 'completed')
                ->pluck('user_id')
                ->toArray();

            $homeworkPending += count(array_diff($offeringStudentIds, $completedIds));
        }

        return [
            'offerings' => $offerings,
            'totalStudents' => count($studentIds),
            'activeThisWeek' => $activeThisWeek,
            'struggling' => $struggling,
            'homeworkPending' => $homeworkPending,
        ];
    }
}
