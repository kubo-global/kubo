<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentTypeFactory extends Factory
{
    public function definition()
    {
        return [
            'school_id' => School::factory(),
            'name' => $this->faker->word,
            'weight' => 0.2500,
            'display_order' => 0,
        ];
    }

    public function test()
    {
        return $this->state([
            'name' => 'Test',
            'weight' => 0.2500,
        ]);
    }

    public function exam()
    {
        return $this->state([
            'name' => 'Exam',
            'weight' => 0.7500,
        ]);
    }
}
