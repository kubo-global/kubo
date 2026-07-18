<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory {
    public function definition()
    {
        return [
            'user_id' => Student::factory(),
            'offering_id' => Offering::factory()
        ];
    }
}