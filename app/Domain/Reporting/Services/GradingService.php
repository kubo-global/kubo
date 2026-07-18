<?php

namespace App\Domain\Reporting\Services;

use App\Models\GradingScale;
use App\Models\School;

class GradingService
{
    private School $school;

    public function __construct(School $school)
    {
        $this->school = $school;
    }

    public function resolve(float $score): ?GradingScale
    {
        return GradingScale::resolve($this->school, $score);
    }
}
