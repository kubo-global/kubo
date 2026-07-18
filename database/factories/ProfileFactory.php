<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => Student::factory()->create()->id,
            'birth_date' => $this->faker->dateTimeBetween('1930-01-01', '2012-12-31'),
            'gender' => $this->faker->randomElement($array = array ('m', 'f')),
            'primary_phone' => $this->faker->phoneNumber,
            'tribe' => $this->faker->randomElement($array = array ('mandinka', 'fula', 'wolof', 'sarahule')),
            'address' => $this->faker->address,
            'comment' => $this->faker->text(200)
        ];
    }
}