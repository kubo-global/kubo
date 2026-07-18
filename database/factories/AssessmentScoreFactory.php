<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentScoreFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => Student::factory(),
            'assessment_id' => Assessment::factory(),
            'score' => rand(0, 10),
            'absent' => false,
        ];
    }
}
