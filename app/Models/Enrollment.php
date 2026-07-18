<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'offering_id'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'user_id');
    }

    public function offering()
    {
        return $this->belongsTo(Offering::class, 'offering_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
