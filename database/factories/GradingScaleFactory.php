<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradingScaleFactory extends Factory
{
    public function definition()
    {
        return [
            'school_id' => School::factory(),
            'label' => 'A',
            'min_score' => 70,
            'max_score' => 100,
            'remark' => 'Excellent',
            'display_order' => 0,
        ];
    }
}
