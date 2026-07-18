<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchoolConfig extends Model
{
    use HasFactory;

    // The per-school config keys in use, in one place so they're discoverable and
    // typo-proof. Read via $school->config(SchoolConfig::KEY, $default).
    public const REPORT_TYPE = 'report_type';                                 // 'term' | 'book'
    public const TERM_CARD_LAYOUT = 'term_card_layout';                       // 'default' | 'albreda'
    public const SCOREBOOK_PERIOD_MODE = 'scorebook_period_mode';             // 'months' | 'tests'
    public const EXPECTED_INSTRUCTIONAL_HOURS = 'expected_instructional_hours'; // {weekday => hours}
    public const GATE_EXERCISES_BY_LESSON_PLAN = 'gate_exercises_by_lesson_plan';
    public const ENABLED_MODULES = 'enabled_modules';

    protected $casts = [
        'value' => 'array',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
