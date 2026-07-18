<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use KuboKolibri\Models\CurriculumMap;

class ExerciseRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function curriculumMap()
    {
        return $this->belongsTo(CurriculumMap::class);
    }

    public function lessonAssignment()
    {
        return $this->belongsTo(LessonAssignment::class);
    }

    public function isHomework(): bool
    {
        return $this->mode === 'homework';
    }

    public function scopeForStudent($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSkill($query, int $skillId)
    {
        return $query->where('skill_id', $skillId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
