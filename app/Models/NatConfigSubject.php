<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * One subject (with its max score) that a given grade sits for the NAT,
 * within a per-year NatConfig.
 */
class NatConfigSubject extends Model
{
    use HasFactory;

    protected $casts = [
        'max_score' => 'integer',
        'display_order' => 'integer',
    ];

    public function natConfig()
    {
        return $this->belongsTo(NatConfig::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
