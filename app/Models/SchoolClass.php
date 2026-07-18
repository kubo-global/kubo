<?php

namespace App\Models;

class SchoolClass extends Model
{
    public $timestamps=false;

    public function offerings()
    {
        return $this->hasMany(Offering::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
