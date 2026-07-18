<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Once-and-done health facts that survive across visits — vaccines,
 * first menstruation. Each student has at most one row; columns hold
 * the date the milestone was first recorded (NULL = not yet).
 */
class StudentHealthMilestone extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'first_menstruated_on' => 'date',
        'hep_a_received_on' => 'date',
        'polio_received_on' => 'date',
        'tetanus_received_on' => 'date',
        'yellow_fever_received_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
