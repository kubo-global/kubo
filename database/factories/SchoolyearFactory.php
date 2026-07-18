<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolyearFactory extends Factory {

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'start' => Carbon::createFromFormat('d/m/Y', "01/09/2019" ),
            'end' => Carbon::createFromFormat('d/m/Y', "31/08/2020")
        ];
    }
}
