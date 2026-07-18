<?php

namespace App\Support;

/**
 * Cheerful, vivid identity colours for the student-login flow. Young pupils
 * recognise "my name is the orange one" faster than they read text, so every
 * child (and every grade badge) gets a stable, bright colour token.
 *
 * The palette is deliberately saturated — no pastels — and the ink colour is
 * picked per hue for the best contrast, so the big bold initial stays legible.
 */
class AvatarColor
{
    /** Saturated, distinct hues that read as playful rather than corporate. */
    private const PALETTE = [
        '#ef4444', // red
        '#f97316', // orange
        '#f59e0b', // amber
        '#eab308', // yellow
        '#22c55e', // green
        '#14b8a6', // teal
        '#3b82f6', // blue
        '#6366f1', // indigo
        '#a855f7', // purple
        '#ec4899', // pink
    ];

    /** @return array{0:string,1:string} [background, ink] for a stable seed. */
    public static function forSeed(int $seed): array
    {
        $bg = self::PALETTE[abs($seed) % count(self::PALETTE)];

        return [$bg, self::inkFor($bg)];
    }

    /** Black or white ink — whichever reads more clearly on the given colour. */
    public static function inkFor(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#1f2937')
            ? '#ffffff'
            : '#1f2937';
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
