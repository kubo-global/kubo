<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staff member's employment record. One per staff user; students never get
 * one. Demographic fields (gender, phone) live on the shared Profile.
 */
class StaffProfile extends Model
{
    protected $fillable = ['user_id', 'prn', 'tin', 'staff_status_id', 'appointed_on', 'confirmed_on'];

    protected $casts = [
        'appointed_on' => 'date',
        'confirmed_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(StaffStatus::class, 'staff_status_id');
    }
}
