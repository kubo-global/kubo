<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssessmentType extends Model
{
    use HasFactory;

    protected $casts = [
        'weight' => 'decimal:4',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }
}
