<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'lesson_date' => 'date',
        'assistant_coordinator_signed_at' => 'datetime',
        'coordinator_signed_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function curriculumTopic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'curriculum_topic_id');
    }
}
