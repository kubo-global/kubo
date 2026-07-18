<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GradingScale extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'label', 'min_score', 'max_score', 'remark', 'purpose', 'display_order', 'grade_min', 'grade_max'];

    protected $casts = [
        'min_score' => 'decimal:2',
        'max_score' => 'decimal:2',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The letter grade for a score. When $grade (the numeric grade level) is given
     * and the school has grade-banded scales, the band covering that grade wins;
     * otherwise the default (null-range) set is used, so single-scale schools are
     * unaffected.
     */
    public static function resolve(School $school, float $score, ?int $grade = null): ?self
    {
        return static::keyFor($school, $grade)
            ->first(fn ($b) => $score >= (float) $b->min_score && $score <= (float) $b->max_score);
    }

    /**
     * The ordered set of grade-key bands that apply to a given grade level: the
     * grade-specific band set when one covers this grade, else the default set
     * (bands with no grade range), else every band.
     */
    public static function keyFor(School $school, ?int $grade = null): \Illuminate\Support\Collection
    {
        $all = $school->gradingScales()
            ->whereNull('purpose')
            ->orderBy('display_order')->orderByDesc('min_score')
            ->get();

        if ($grade === null) {
            return $all;
        }

        $ranged = $all->filter(fn ($b) => $b->grade_min !== null && $grade >= $b->grade_min && $grade <= $b->grade_max);
        if ($ranged->isNotEmpty()) {
            return $ranged->values();
        }

        $default = $all->filter(fn ($b) => $b->grade_min === null);

        return ($default->isNotEmpty() ? $default : $all)->values();
    }
}
