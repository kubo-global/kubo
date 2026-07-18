<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\NatConfig;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeds the Gambia NAT configuration (which grades sit it + their subjects) per
 * school year. The NAT is a census at Grades 3, 5 (and 8 in Upper Basic); a Lower
 * Basic school like The Swallow runs Grades 3 and 5. Idempotent.
 *
 * Subjects per grade follow the WAEC pattern: lower grades use a combined
 * "Integrated studies"; it splits into Science + S.E.S. higher up.
 */
class GambiaNatConfigSeeder extends Seeder
{
    /** grade name => subject names that grade sits for the NAT */
    private const SUBJECTS_BY_GRADE = [
        'Grade 3' => ['English language', 'Mathematics', 'Integrated studies'],
        'Grade 5' => ['English language', 'Mathematics', 'Science', 'S.E.S.'],
        // 'Grade 8' => [...]  // add when a school activates Upper Basic
    ];

    /** Restrict to recent years (KUBO-era); null = every school year. */
    private const YEAR_NAMES = ['2023-2024', '2024-2025', '2025-2026'];

    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            $this->command?->warn('No school found, skipping GambiaNatConfigSeeder.');

            return;
        }

        $years = Schoolyear::when(self::YEAR_NAMES, fn ($q) => $q->whereIn('name', self::YEAR_NAMES))->get();

        foreach ($years as $year) {
            $config = NatConfig::firstOrCreate(
                ['school_id' => $school->id, 'schoolyear_id' => $year->id],
                ['enabled' => true, 'label' => 'National Assessment Test'],
            );

            foreach (self::SUBJECTS_BY_GRADE as $gradeName => $subjectNames) {
                $grade = Grade::where('name', $gradeName)->first();
                if (! $grade) {
                    continue;
                }
                foreach ($subjectNames as $i => $subjectName) {
                    $subject = Subject::where('name', $subjectName)->first();
                    if (! $subject) {
                        continue;
                    }
                    $config->subjects()->firstOrCreate(
                        ['grade_id' => $grade->id, 'subject_id' => $subject->id],
                        ['max_score' => 100, 'display_order' => $i],
                    );
                }
            }
        }

        $this->command?->info('Gambia NAT config seeded for '.$years->count().' school year(s).');
    }
}
