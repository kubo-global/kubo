<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;

class AuthoredExercise extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'randomize' => 'boolean',
            'channel_synced_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    public function subject()
    {
        return $this->belongsTo(\App\Models\Subject::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(AuthoredQuestion::class)->orderBy('sort_order');
    }
}
