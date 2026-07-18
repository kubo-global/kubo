<?php

namespace Database\Factories;

use App\Models\AssessmentType;
use App\Models\Subject;
use App\Models\Offering;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    public function definition()
    {
        return [
            'assessment_type_id' => AssessmentType::factory(),
            'subject_id' => Subject::factory(),
            'offering_id' => Offering::factory(),
            'term_id' => Term::factory(),
            'date' => Carbon::createFromFormat('d/m/Y', '10/10/2019'),
            'name' => $this->faker->word,
            'max_score' => 10,
        ];
    }
}
