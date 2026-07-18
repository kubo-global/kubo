<?php

namespace App\Models;

class WoundCareVisit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'visited_on' => 'date',
    ];

    public function woundCase()
    {
        return $this->belongsTo(WoundCase::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
