<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A class's instructional hours for one school day: actual hours taught and hours
 * lost. Expected hours are derived from the timetable, not stored.
 */
class InstructionalHour extends Model
{
    use HasFactory;

    protected $table = 'instructional_hours';

    protected $fillable = ['offering_id', 'date', 'actual_hours', 'lost_hours', 'remarks', 'recorded_by'];

    protected $casts = [
        'date' => 'date',
        'actual_hours' => 'float',
        'lost_hours' => 'float',
    ];

    public function offering()
    {
        return $this->belongsTo(Offering::class);
    }
}
