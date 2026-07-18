<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->company . ' School',
            'motto' => $this->faker->sentence(4),
            'address' => $this->faker->address,
            'timezone' => 'Africa/Lagos',
        ];
    }
}
