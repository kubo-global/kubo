<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;

class LessonAssignment extends Model
{
    protected $guarded = [];

    public function offering()
    {
        return $this->belongsTo(\App\Models\Offering::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function teacher()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }

    /**
     * Specific students this assignment targets.
     * Empty means whole class.
     */
    public function students()
    {
        return $this->belongsToMany(\App\Models\User::class, 'lesson_assignment_students');
    }

    /**
     * Check if this assignment applies to a specific student.
     * If no student targeting rows exist, it's for the whole class.
     */
    public function isForStudent(int $userId): bool
    {
        if (!$this->students()->exists()) {
            return true; // whole class
        }

        return $this->students()->where('users.id', $userId)->exists();
    }

    public function scopeForOffering($query, int $offeringId)
    {
        return $query->where('offering_id', $offeringId);
    }

    public function scopeForWeek($query, int $week)
    {
        return $query->where('week_number', $week);
    }
}
