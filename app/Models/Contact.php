<?php

namespace App\Models;

class Contact extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function getInitials(){
        return substr($this->first_name, 0, 1) . substr($this->last_name,0,1);
    }
}
