<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grade extends Model
{
    use HasFactory;

    public $timestamps=false;

    protected $fillable = ['name', 'color', 'school_id', 'student_login_enabled'];

    protected $casts = ['student_login_enabled' => 'boolean'];

    /**
     * Broad palette tuned to harmonise with the brand colours. The 5 brand hues
     * (blue/green/gold/red/pink) plus 5 coordinated additions, all at a similar
     * muted tone. key => [light bg, dark text, solid swatch] as hex (applied via
     * inline styles, so the hues aren't limited to Tailwind's scales).
     *
     * The [bg, text] pair of every hue meets WCAG AA contrast (>= 4.5:1) so the
     * grade badges are legible — keep new/edited hues above that threshold.
     */
    public const COLORS = [
        'blue'   => ['#e7ebf4', '#34486f', '#3d5494'],
        'sky'    => ['#e2f0fa', '#1f6390', '#3b9fd6'],
        'teal'   => ['#dff3f0', '#1c6f66', '#2aa79b'],
        'green'  => ['#ddf0e5', '#296643', '#47b174'],
        'lime'   => ['#eef3dc', '#5a6b22', '#8caa3e'],
        'gold'   => ['#fdf3d4', '#7c6200', '#f0b429'],
        'orange' => ['#fceadd', '#9a4f1d', '#e8843c'],
        'red'    => ['#fbe0e0', '#8b2222', '#dd4143'],
        'pink'   => ['#fce7ed', '#ad2d53', '#e15d83'],
        'purple' => ['#efe7f7', '#5a3a82', '#8a5cc0'],
    ];

    /** Resolved [bg, text, swatch] hex — the chosen colour, else a stable default by id. */
    public function colorTriplet(): array
    {
        $key = ($this->color && isset(static::COLORS[$this->color]))
            ? $this->color
            : array_keys(static::COLORS)[$this->id % count(static::COLORS)];

        return static::COLORS[$key];
    }

    /** Inline style for a coloured avatar (light bg + dark text). */
    public function colorStyle(): string
    {
        [$bg, $text] = $this->colorTriplet();

        return "background-color: {$bg}; color: {$text};";
    }

    /**
     * Badge style for a class, deepening the grade's tint per section so several classes
     * in one grade share the hue but read as distinct shades. Index 0 is the base
     * (unchanged); later sections blend toward the saturated colour, and the text colour
     * is picked for >=4.5:1 contrast against whatever shade results.
     */
    public function sectionColorStyle(int $index = 0): string
    {
        [$bg, $text, $dot] = $this->colorTriplet();
        if ($index < 1) {
            return "background-color: {$bg}; color: {$text};";
        }

        // Stay in the legible light range — past ~0.58 toward the saturated colour the
        // tint hits a mid-tone that neither dark nor white text can meet 4.5:1 against.
        $shaded = self::blendHex($bg, $dot, min(0.30 + ($index - 1) * 0.13, 0.55));
        $ink = self::contrast($shaded, $text) >= 4.5
            ? $text
            : (self::contrast($shaded, '#ffffff') >= self::contrast($shaded, '#1f2937') ? '#ffffff' : '#1f2937');

        return "background-color: {$shaded}; color: {$ink};";
    }

    private static function blendHex(string $from, string $to, float $t): string
    {
        [$fr, $fg, $fb] = sscanf($from, '#%02x%02x%02x');
        [$tr, $tg, $tb] = sscanf($to, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x',
            (int) round($fr + ($tr - $fr) * $t),
            (int) round($fg + ($tg - $fg) * $t),
            (int) round($fb + ($tb - $fb) * $t));
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

    /** Inline style for a small solid colour dot. */
    public function dotStyle(): string
    {
        return 'background-color: '.$this->colorTriplet()[2].';';
    }

    public function offerings()
    {
        return $this->hasMany(Offering::class, 'grade_id');
    }
}
