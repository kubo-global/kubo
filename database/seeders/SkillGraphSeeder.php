<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use KuboKolibri\Models\CurriculumMap;
use App\Domain\Learning\Models\Skill;

/**
 * Seeds a skill prerequisite graph for Early Math, linked to Khan Academy content.
 *
 * The graph:
 *
 *   Count small numbers (Nursery 1)
 *       ↓
 *   Count in order (Nursery 1)
 *       ↓
 *   Count objects (Nursery 2)
 *       ↓
 *   Compare numbers (Nursery 2)
 *       ↓
 *   Numbers to 100 (Nursery 3)
 *       ↓
 *   Add within 5 (Primary 1)
 *       ↓
 *   Subtract within 5 (Primary 1)
 *       ↓
 *   Add within 10 (Primary 1)
 *       ↓
 *   Subtract within 10 (Primary 1)
 *       ↓
 *   Make 10 (Primary 2)
 *       ↓
 *   Addition word problems (Primary 2)
 *       ↓
 *   Subtraction word problems (Primary 2)
 */
class SkillGraphSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('name', 'Default School')->first();
        $math = Subject::where('name', 'Mathematics')->first();

        // Get grades (created by BasicSchoolSeeder)
        $grades = Grade::all()->keyBy('name');

        // Create skills with progressive levels and grade associations
        $skills = [
            ['name' => 'Count small numbers',        'level' => 1,  'grade' => 'Nursery 1',  'exercise' => '2039e90031405286b021b82a713cb880', 'video' => 'bee740d7a210545ab047b58fb61b9420'],
            ['name' => 'Count in order',              'level' => 2,  'grade' => 'Nursery 1',  'exercise' => '8bcf3959f18f57c38d64615254fe3758', 'video' => 'd9a995b0e23c536eb097fb696593c151'],
            ['name' => 'Count objects',               'level' => 3,  'grade' => 'Nursery 2',  'exercise' => '8c3d04ac301f56899017ff379340256b', 'video' => '9757286215535f04ba982eb8657e33e1'],
            ['name' => 'Count in pictures',           'level' => 4,  'grade' => 'Nursery 2',  'exercise' => 'ace96c08e0295776a248a35df51d2742', 'video' => 'c6751283222654a08e1e7d6ca0bf8cb9'],
            ['name' => 'Compare numbers',             'level' => 5,  'grade' => 'Nursery 2',  'exercise' => '7edf4abae4f15ad2baa0b3e677a9f6c6', 'video' => 'ea152432f39c5cafbbf8ed6155e71aa8'],
            ['name' => 'Numbers to 100',              'level' => 6,  'grade' => 'Nursery 3',  'exercise' => '5e876dc3c95e5e6dab5e54558f3058de', 'video' => 'ad4c104a895d52aab8057652116a4697'],
            ['name' => 'Add within 5',                'level' => 7,  'grade' => 'Primary 1',  'exercise' => '27aa4bd211f7555a8f49202ca835819d', 'video' => '7da76df5199a57eabf43b95cd7c175b3'],
            ['name' => 'Subtract within 5',           'level' => 8,  'grade' => 'Primary 1',  'exercise' => '995e4b649447575a86a25390154e193c', 'video' => '8ba2a6362cec57208f70f31f12f07fca'],
            ['name' => 'Add within 10',               'level' => 9,  'grade' => 'Primary 1',  'exercise' => 'd8586972312d558e8e245c75f3aa4400', 'video' => '879c7dea9cd858618e89779f685d2478'],
            ['name' => 'Subtract within 10',          'level' => 10, 'grade' => 'Primary 1',  'exercise' => 'c18623de835e56acb68aa3090516f058', 'video' => 'a340a56059cc573b95610d2c32828f50'],
            ['name' => 'Make 10',                     'level' => 11, 'grade' => 'Primary 2',  'exercise' => '55f39fb5c7a950cd890759ed338ae631', 'video' => 'dd2fea461fd353cb95026746434cad2b'],
            ['name' => 'Addition word problems',      'level' => 12, 'grade' => 'Primary 2',  'exercise' => '4a3d05a957f45c62b8089bfaab350230', 'video' => '46d958f23ee850e680765891e17adac5'],
            ['name' => 'Subtraction word problems',   'level' => 13, 'grade' => 'Primary 2',  'exercise' => 'bc3b9d781b305274a7839d9abed53a0c', 'video' => '42fa118b47b2598db0e290cc2df21e48'],
        ];

        $createdSkills = [];
        foreach ($skills as $data) {
            $gradeId = isset($grades[$data['grade']]) ? $grades[$data['grade']]->id : null;

            $skill = Skill::firstOrCreate(
                ['school_id' => $school->id, 'subject_id' => $math->id, 'name' => $data['name']],
                ['level' => $data['level'], 'grade_id' => $gradeId]
            );

            // Link to Kolibri content
            $exerciseMap = CurriculumMap::where('kolibri_node_id', $data['exercise'])->first();
            $videoMap = CurriculumMap::where('kolibri_node_id', $data['video'])->first();

            if ($exerciseMap && !$skill->content()->where('curriculum_map_id', $exerciseMap->id)->exists()) {
                $skill->content()->attach($exerciseMap->id, ['role' => 'practice']);
            }
            if ($videoMap && !$skill->content()->where('curriculum_map_id', $videoMap->id)->exists()) {
                $skill->content()->attach($videoMap->id, ['role' => 'teach']);
            }

            $createdSkills[] = $skill;
        }

        // Create prerequisite edges: each skill requires the previous one
        for ($i = 1; $i < count($createdSkills); $i++) {
            $skill = $createdSkills[$i];
            $prereq = $createdSkills[$i - 1];

            if (!$skill->prerequisites()->where('prerequisite_id', $prereq->id)->exists()) {
                $skill->prerequisites()->attach($prereq->id);
            }
        }
    }
}
