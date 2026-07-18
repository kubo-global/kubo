<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Term extends Model
{
    use HasFactory;

    public $timestamps =false;
    protected $casts=['start' => 'datetime','end' => 'datetime'];
        
    public static function current() : ?Term
    {
        return Term::where('start', '<=', Carbon::now())
            ->where('end', '<=', Carbon::now())
            ->first();
    }

    /**
     * A term is "locked" once its end date has passed. Score entry for a
     * locked term is restricted to headmaster/admin so a regular teacher
     * can't quietly fudge old scores after report cards have gone out.
     */
    public function isLocked(): bool
    {
        return $this->end && Carbon::now()->isAfter($this->end);
    }

    public function schoolyear() : BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function subjects() : BelongsToMany
    {
        return $this->belongsToMany(Subject::class);
    }
}
