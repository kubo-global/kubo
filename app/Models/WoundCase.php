<?php

namespace App\Models;

class WoundCase extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opened_on' => 'date',
        'closed_on' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visits()
    {
        return $this->hasMany(WoundCareVisit::class)->orderBy('visited_on');
    }

    public function isOpen(): bool
    {
        return $this->closed_on === null;
    }
}
