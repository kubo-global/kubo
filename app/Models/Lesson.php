<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * One cell of a class's weekly timetable: a subject (and optionally a teacher)
 * taught in a given period on a given weekday for one offering (class).
 */
class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['offering_id', 'period_id', 'day', 'length', 'subject_id', 'teacher_id'];

    protected $casts = ['length' => 'integer'];

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

    public function offering()
    {
        return $this->belongsTo(Offering::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
