<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use App\Models\GradingScale;
use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::create([
            'name' => 'Default School',
            'motto' => 'Excellence in Education',
            'timezone' => 'Africa/Lagos',
        ]);

        // Assessment types matching existing test/exam weights
        AssessmentType::create([
            'school_id' => $school->id,
            'name' => 'Test',
            'weight' => 0.2500,
            'display_order' => 1,
        ]);

        AssessmentType::create([
            'school_id' => $school->id,
            'name' => 'Exam',
            'weight' => 0.7500,
            'display_order' => 2,
        ]);

        // Gambian lower-basic grade key (grade 1 = best). Editable per school in Settings.
        $grades = [
            ['label' => '1', 'min_score' => 80, 'max_score' => 100, 'remark' => 'Excellent', 'display_order' => 1],
            ['label' => '4', 'min_score' => 70, 'max_score' => 79.99, 'remark' => 'Very Good', 'display_order' => 2],
            ['label' => '5', 'min_score' => 60, 'max_score' => 69.99, 'remark' => 'Good', 'display_order' => 3],
            ['label' => '6', 'min_score' => 50, 'max_score' => 59.99, 'remark' => 'Average', 'display_order' => 4],
            ['label' => '8', 'min_score' => 40, 'max_score' => 49.99, 'remark' => 'Pass', 'display_order' => 5],
            ['label' => '9', 'min_score' => 0, 'max_score' => 39.99, 'remark' => 'Fail', 'display_order' => 6],
        ];

        foreach ($grades as $grade) {
            GradingScale::create(array_merge(['school_id' => $school->id], $grade));
        }
    }
}
