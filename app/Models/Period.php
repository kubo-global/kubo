<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A school-wide period (timetable row): a teaching slot or a break, with an
 * optional clock time. The same set of periods structures every class's
 * weekly timetable.
 */
class Period extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'label', 'start_time', 'end_time', 'is_break', 'display_order'];

    protected $casts = ['is_break' => 'boolean'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /** Periods in display order for a school. */
    public static function ordered(?int $schoolId = null)
    {
        return static::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /** "08:00 – 08:40" or null when no times are set. */
    public function timeRange(): ?string
    {
        if (! $this->start_time && ! $this->end_time) {
            return null;
        }
        $fmt = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('H:i') : '';
        return trim($fmt($this->start_time).' – '.$fmt($this->end_time), ' –');
    }
}
