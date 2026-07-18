<?php

namespace Database\Factories;

use App\Models\Schoolyear;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class TermFactory extends Factory {
    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'start' => Carbon::createFromFormat('d/m/Y', "01/09/2019"),
            'end' => Carbon::createFromFormat('d/m/Y', "31/12/2019"),
            'schoolyear_id' => Schoolyear::factory()
        ];
    }
}