<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherAssignment extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function offering()
    {
        return $this->belongsTo(Offering::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
