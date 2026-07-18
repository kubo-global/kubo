<?php

namespace App\Models;

class IncidentReport extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'closed_on' => 'date',
        'temperature' => 'decimal:1',
        'first_aid_given' => 'boolean',
        'parents_contacted' => 'boolean',
        'sent_home' => 'boolean',
        'taken_to_hospital' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Each time somebody checked back on the child. Same idea as a wound case's visits. */
    public function followUps()
    {
        return $this->hasMany(IncidentFollowUp::class)->orderBy('noted_on');
    }

    /** Open = still needs follow-up. Same convention as WoundCase. */
    public function isOpen(): bool
    {
        return $this->closed_on === null;
    }

    /**
     * Comma-separated summary of which action-taken boxes are checked,
     * for the timeline card.
     */
    public function actionLabel(): string
    {
        $parts = [];
        if ($this->first_aid_given) $parts[] = 'first aid';
        if ($this->medication_given) $parts[] = 'medication: '.$this->medication_given;
        if ($this->parents_contacted) $parts[] = 'parents contacted';
        if ($this->sent_home) $parts[] = 'sent home';
        if ($this->taken_to_hospital) $parts[] = 'hospital';
        return implode(' + ', $parts);
    }
}
