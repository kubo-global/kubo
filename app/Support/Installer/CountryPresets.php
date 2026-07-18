<?php

namespace App\Support\Installer;

/**
 * Country-specific onboarding presets for the installer. Only countries we
 * explicitly support get a ready-made academic structure (grades, subjects,
 * NAT); every other country gets a blank/minimal setup so we never push a
 * Gambian curriculum onto a school it doesn't fit. Add a country by giving it
 * a preset() entry and listing it in countries().
 */
class CountryPresets
{
    /** Countries shown in the installer's country picker. */
    public static function countries(): array
    {
        return [
            'GM' => 'The Gambia',
            'OTHER' => 'Other / not listed',
        ];
    }

    /** Does this country ship a ready-made academic structure? */
    public static function has(string $country): bool
    {
        return static::for($country) !== null;
    }

    /** The preset for a country, or null when we have none. */
    public static function for(string $country): ?array
    {
        return match ($country) {
            'GM' => static::gambia(),
            default => null,
        };
    }

    /**
     * The Gambian (WAEC / Lower Basic) preset: Nursery 1-3 + Grade 1-6, the
     * standard primary subject set in curriculum order, and the NAT census
     * grades (3 and 5) with their subjects.
     */
    private static function gambia(): array
    {
        return [
            'grades' => [
                'Nursery 1', 'Nursery 2', 'Nursery 3',
                'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
            ],
            'subjects' => [
                'English language', 'Mathematics', 'Integrated studies', 'Science', 'S.E.S.',
                'Spelling/Dictation', 'Phonics', 'Verbal aptitude', 'Quantitative', 'Reading',
                'National Language', 'French', 'Information Technology', 'Art and craft',
                'Physical Education', 'Religious Knowledge', 'Health',
            ],
            // Subjects that start unticked in the wizard: most public schools don't
            // teach these, so they opt in rather than delete them afterwards.
            'optional_subjects' => ['French', 'Health'],
            // grade => the subjects that grade sits for the National Assessment Test
            'nat' => [
                'Grade 3' => ['English language', 'Mathematics', 'Integrated studies'],
                'Grade 5' => ['English language', 'Mathematics', 'Science', 'S.E.S.'],
            ],
            // The Gambian lower-basic grade key (grade 1 = best); editable in Settings.
            'grade_scale' => [
                ['label' => '1', 'min_score' => 80, 'max_score' => 100,   'remark' => 'Excellent', 'display_order' => 1],
                ['label' => '4', 'min_score' => 70, 'max_score' => 79.99, 'remark' => 'Very Good', 'display_order' => 2],
                ['label' => '5', 'min_score' => 60, 'max_score' => 69.99, 'remark' => 'Good',      'display_order' => 3],
                ['label' => '6', 'min_score' => 50, 'max_score' => 59.99, 'remark' => 'Average',   'display_order' => 4],
                ['label' => '8', 'min_score' => 40, 'max_score' => 49.99, 'remark' => 'Pass',      'display_order' => 5],
                ['label' => '9', 'min_score' => 0,  'max_score' => 39.99, 'remark' => 'Fail',      'display_order' => 6],
            ],
            'timezone' => 'Africa/Banjul',
        ];
    }
}
