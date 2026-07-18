<?php

namespace App\Models;

class MedicalNote extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'noted_on' => 'date',
        'temperature' => 'decimal:1',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
