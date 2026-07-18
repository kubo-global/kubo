<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A configurable staff employment status (DHMC, HMA, SMC, QT, SMB, ECD, …),
 * editable in Settings and shown on the staff list.
 */
class StaffStatus extends Model
{
    protected $fillable = ['school_id', 'label', 'description', 'display_order'];

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }
}
