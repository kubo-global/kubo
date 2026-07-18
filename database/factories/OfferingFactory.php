<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferingFactory extends Factory {
    public function definition()
    {
        return [
            'schoolyear_id' => Schoolyear::factory(),
            'grade_id' => Grade::factory(),
            'activated' => 1
        ];
    }
}