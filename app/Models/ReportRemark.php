<?php

namespace App\Models;

/**
 * A pupil's per-term report feedback (conduct + general remark). Keyed by
 * (enrollment, term); written from the "Prepare reports" screen and read at
 * print time so the card shows typed feedback instead of a blank to fill by hand.
 */
class ReportRemark extends Model
{
    protected $guarded = [];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
