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

    /**
     * The three scorebook buckets in tests mode (The Swallow): Test 1 lives in
     * the term's first month, Test 2 in the second, the Exam in the last. The
     * scorebook's period dropdown and slot-based assessment creation both key
     * off these months, so they must stay in lockstep.
     *
     * @return array<string, \Illuminate\Support\Carbon> slot => a date inside its month
     */
    public function testsBucketMonths(): array
    {
        $start = \Illuminate\Support\Carbon::parse($this->start)->startOfMonth();
        $end = \Illuminate\Support\Carbon::parse($this->end)->startOfMonth();

        $test1 = $start->copy();
        $exam = $end->gt($start) ? $end->copy() : $start->copy()->addMonths(2);
        $test2 = $start->copy()->addMonth();
        if ($test2->gte($exam)) {
            $test2 = $exam->copy()->subMonth();
        }
        if ($test2->lte($test1)) {
            $test2 = $test1->copy()->addMonth();
        }

        return [
            'Test 1' => $test1->copy()->addDays(14),
            'Test 2' => $test2->copy()->addDays(14),
            'Exam' => $exam->copy()->addDays(14),
        ];
    }
}
