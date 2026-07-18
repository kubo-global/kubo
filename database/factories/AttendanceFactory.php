<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition()
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'offering_id' => null,
            'date' => $this->faker->date(),
            'status' => AttendanceStatus::Present,
            'type' => AttendanceType::Student,
            'recorded_by' => null,
            'note' => null,
        ];
    }
}
