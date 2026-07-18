<?php

namespace Database\Seeders;

/**
 * WHO Growth Reference 2007 medians, for seeding believable growth series.
 *
 * The demo used to draw height and weight at random (110-160 cm, 20-45 kg),
 * which put a six-year-old at 136 cm and 33 kg: far outside +2 SD, and a single
 * lonely dot on the chart. Measurements now hang off the same curves the chart
 * draws, so a pupil sits near the median and grows along their own line.
 *
 * Each row is [age in months, median, one SD]. Height-for-age and BMI-for-age
 * cover the whole 5-19 range; weight is derived from BMI so it stays consistent
 * with the height (and with the BMI chart).
 */
class GrowthCurve
{
    /** [months, median cm, 1 SD in cm] */
    private const HEIGHT = [
        'M' => [
            [61, 110.3, 4.6], [72, 116.0, 4.9], [84, 121.7, 5.3], [96, 127.3, 5.6],
            [108, 132.6, 6.0], [120, 137.8, 6.4], [132, 143.1, 6.7], [144, 149.1, 7.1],
            [156, 156.0, 7.5], [168, 163.2, 7.7], [180, 169.0, 7.8], [192, 172.9, 7.8],
        ],
        'F' => [
            [61, 109.6, 4.8], [72, 115.1, 5.1], [84, 120.8, 5.5], [96, 126.6, 5.8],
            [108, 132.5, 6.1], [120, 138.6, 6.4], [132, 145.0, 6.6], [144, 151.2, 6.9],
            [156, 156.4, 6.9], [168, 159.8, 6.9], [180, 161.7, 6.8], [192, 162.5, 6.8],
        ],
    ];

    /** [months, median kg/m², 1 SD] */
    private const BMI = [
        'M' => [
            [61, 15.3, 1.3], [72, 15.3, 1.5], [84, 15.5, 1.5], [96, 15.7, 1.7],
            [108, 16.0, 1.9], [120, 16.4, 2.1], [132, 16.9, 2.3], [144, 17.5, 2.4],
            [156, 18.2, 2.6], [168, 19.0, 2.8], [180, 19.8, 2.9], [192, 20.5, 3.0],
        ],
        'F' => [
            [61, 15.2, 1.7], [72, 15.3, 1.7], [84, 15.4, 1.9], [96, 15.7, 2.0],
            [108, 16.1, 2.2], [120, 16.6, 2.4], [132, 17.2, 2.7], [144, 18.0, 2.8],
            [156, 18.8, 3.0], [168, 19.6, 3.1], [180, 20.2, 3.3], [192, 20.7, 3.4],
        ],
    ];

    /** Height in cm at this age, z standard deviations away from the median. */
    public static function heightCm(string $sex, float $ageMonths, float $z): float
    {
        [$median, $sd] = self::at(self::HEIGHT[self::sex($sex)], $ageMonths);

        return $median + $z * $sd;
    }

    /**
     * Weight in kg, derived from BMI-for-age and the height we just gave them,
     * so height, weight and BMI tell the same story.
     */
    public static function weightKg(string $sex, float $ageMonths, float $heightCm, float $z): float
    {
        [$median, $sd] = self::at(self::BMI[self::sex($sex)], $ageMonths);
        $bmi = $median + $z * $sd;

        return $bmi * (($heightCm / 100) ** 2);
    }

    /** Linear interpolation between the two rows around this age. */
    private static function at(array $table, float $months): array
    {
        $months = max($table[0][0], min($months, $table[count($table) - 1][0]));

        for ($i = 0; $i < count($table) - 1; $i++) {
            [$m1, $median1, $sd1] = $table[$i];
            [$m2, $median2, $sd2] = $table[$i + 1];

            if ($months <= $m2) {
                $t = ($months - $m1) / ($m2 - $m1);

                return [$median1 + $t * ($median2 - $median1), $sd1 + $t * ($sd2 - $sd1)];
            }
        }

        return [$table[count($table) - 1][1], $table[count($table) - 1][2]];
    }

    private static function sex(?string $sex): string
    {
        return strtoupper((string) $sex) === 'F' ? 'F' : 'M';
    }
}
