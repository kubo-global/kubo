<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherAssignmentFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'offering_id' => Offering::factory(),
            'subject_id' => null,
            'is_class_teacher' => false,
        ];
    }
}
