<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeds the building blocks for National Assessment Test (NAT) reporting:
 * the NAT assessment type, the WAEC subjects, and the fail/pass/mastery
 * GradingScale bands (purpose = 'nat', thresholds < 40 / >= 80).
 *
 * Idempotent — safe to run on an existing install.
 */
class NatSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();

        if (! $school) {
            $this->command?->warn('No school found — skipping NatSeeder.');

            return;
        }

        AssessmentType::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'National Assessment Test'],
            ['weight' => 0, 'display_order' => 99],
        );

        foreach (['English', 'Mathematics', 'Integrated Studies', 'Science', 'Social & Environmental Studies'] as $name) {
            Subject::firstOrCreate(['name' => $name]);
        }

        // fail < 40, pass 40–79, mastery >= 80 (cumulative pass = fail-complement at report time)
        $bands = [
            ['label' => 'Fail', 'min' => 0, 'max' => 39.99],
            ['label' => 'Pass', 'min' => 40, 'max' => 79.99],
            ['label' => 'Mastery', 'min' => 80, 'max' => 100],
        ];

        foreach ($bands as $i => $band) {
            GradingScale::firstOrCreate(
                ['school_id' => $school->id, 'purpose' => 'nat', 'label' => $band['label']],
                ['min_score' => $band['min'], 'max_score' => $band['max'], 'remark' => $band['label'], 'display_order' => $i],
            );
        }
    }
}
