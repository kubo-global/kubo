<?php

namespace App\Models;

class IncidentFollowUp extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'noted_on' => 'date',
    ];

    public function incident()
    {
        return $this->belongsTo(IncidentReport::class, 'incident_report_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
