<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSkill extends Model
{
    protected $guarded = [];

    protected $casts = [
        'mastery' => 'decimal:2',
        'last_attempted_at' => 'datetime',
        'mastered_at' => 'datetime',
        'next_review_week' => 'integer',
        'review_interval_index' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function isMastered(): bool
    {
        return $this->status === 'mastered';
    }

    public function scopeForStudent($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeMastered($query)
    {
        return $query->where('status', 'mastered');
    }

    public function scopeStruggling($query)
    {
        return $query->where('status', 'struggling');
    }

    public function scopeMasteredOrStruggling($query)
    {
        return $query->whereIn('status', ['mastered', 'struggling']);
    }

    public function scopeReviewDue($query, int $currentWeek)
    {
        return $query->whereIn('status', ['mastered', 'struggling'])
            ->whereNotNull('next_review_week')
            ->where('next_review_week', '<=', $currentWeek);
    }

    public function isStruggling(): bool
    {
        return $this->status === 'struggling';
    }

    public function isReviewDue(int $currentWeek): bool
    {
        return in_array($this->status, ['mastered', 'struggling'])
            && $this->next_review_week !== null
            && $this->next_review_week <= $currentWeek;
    }
}
