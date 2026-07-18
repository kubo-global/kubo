<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use KuboKolibri\Models\CurriculumMap;
use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\LessonAssignment;
use App\Domain\Learning\Models\Skill;
use App\Domain\Learning\Models\StudentSkill;

class LearnBrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        // School
        $school = School::firstOrCreate(
            ['name' => 'Browser Test School'],
            ['kolibri_facility_id' => 'browser-test-facility'],
        );

        // Academic structure — reuse existing school year if one exists
        $schoolyear = Schoolyear::orderByDesc('id')->first()
            ?? Schoolyear::create(['name' => '2025-2026', 'start' => '2025-09-01', 'end' => '2026-08-31']);
        Term::firstOrCreate(
            ['name' => 'Term 1', 'schoolyear_id' => $schoolyear->id],
            ['start' => '2025-09-01', 'end' => '2025-12-31'],
        );
        Term::firstOrCreate(
            ['name' => 'Term 2', 'schoolyear_id' => $schoolyear->id],
            ['start' => '2026-01-12', 'end' => '2026-04-10'],
        );

        $math = Subject::firstOrCreate(['name' => 'Mathematics']);
        $grade = Grade::firstOrCreate(['name' => 'Grade 3']);

        $offering = Offering::firstOrCreate(
            ['schoolyear_id' => $schoolyear->id, 'grade_id' => $grade->id],
            ['activated' => true],
        );

        // Student user (password: secret)
        $student = User::firstOrCreate(
            ['email' => 'teststudent@kubo.test'],
            [
                'first_name' => 'Test',
                'last_name' => 'Student',
                'password' => bcrypt('secret'),
            ],
        );
        if (!$student->hasRole('student')) {
            $student->assignRole('student');
        }
        DB::table('users')->where('id', $student->id)
            ->update(['kolibri_user_id' => 'browser-test-kolibri-user']);

        Enrollment::firstOrCreate([
            'user_id' => $student->id,
            'offering_id' => $offering->id,
        ]);

        // Teacher for assignments
        $teacher = User::firstOrCreate(
            ['email' => 'testteacher@kubo.test'],
            [
                'first_name' => 'Test',
                'last_name' => 'Teacher',
                'password' => bcrypt('secret'),
            ],
        );
        if (!$teacher->hasRole('teacher')) {
            $teacher->assignRole('teacher');
        }

        // Skills with practice content
        $skills = [
            ['name' => 'Counting to 100', 'level' => 1],
            ['name' => 'Addition within 20', 'level' => 2],
            ['name' => 'Subtraction within 20', 'level' => 3],
            ['name' => 'Multiplication basics', 'level' => 4],
        ];

        foreach ($skills as $skillData) {
            $skill = Skill::firstOrCreate(
                ['school_id' => $school->id, 'name' => $skillData['name']],
                [
                    'subject_id' => $math->id,
                    'grade_id' => $grade->id,
                    'level' => $skillData['level'],
                ],
            );

            $map = CurriculumMap::firstOrCreate(
                ['school_id' => $school->id, 'kolibri_node_id' => 'node-' . $skill->id],
                [
                    'subject_id' => $math->id,
                    'kolibri_channel_id' => '00000000-0000-0000-0000-000000000000',
                    'content_kind' => 'exercise',
                ],
            );

            DB::table('skill_content')->insertOrIgnore([
                'skill_id' => $skill->id,
                'curriculum_map_id' => $map->id,
                'role' => 'practice',
                'approved' => true,
            ]);
        }

        // Prerequisite edges: each skill requires the previous one
        $allSkills = Skill::where('school_id', $school->id)->orderBy('level')->get();
        for ($i = 1; $i < $allSkills->count(); $i++) {
            DB::table('skill_edges')->insertOrIgnore([
                'skill_id' => $allSkills[$i]->id,
                'prerequisite_id' => $allSkills[$i - 1]->id,
            ]);
        }

        // Master the first skill
        $firstSkill = $allSkills->first();
        StudentSkill::firstOrCreate(
            ['user_id' => $student->id, 'skill_id' => $firstSkill->id],
            [
                'status' => 'mastered',
                'mastery' => 92.0,
                'attempts' => 3,
                'mastered_at' => now(),
            ],
        );

        // A completed run for the first skill (to show history)
        $firstMap = CurriculumMap::where('kolibri_node_id', 'node-' . $firstSkill->id)->first();
        ExerciseRun::firstOrCreate(
            ['user_id' => $student->id, 'skill_id' => $firstSkill->id, 'status' => 'completed'],
            [
                'curriculum_map_id' => $firstMap->id,
                'mode' => 'free',
                'started_at' => now()->subHour(),
                'completed_at' => now()->subMinutes(50),
                'total_questions' => 5,
                'correct_answers' => 4,
                'wrong_answers' => 1,
                'score' => 80.00,
            ],
        );

        // Assign second skill as this week's lesson
        $secondSkill = $allSkills[1];
        $currentWeek = \App\Support\SchoolCalendar::currentSchoolWeek();
        LessonAssignment::firstOrCreate(
            ['offering_id' => $offering->id, 'skill_id' => $secondSkill->id, 'week_number' => $currentWeek],
            ['assigned_by' => $teacher->id],
        );

        $this->command->info("Browser test data seeded. Login: teststudent@kubo.test / secret");
    }
}
