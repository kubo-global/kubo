<?php

namespace App\Models;


class Topic extends Model
{
    public $timestamps = false;

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
