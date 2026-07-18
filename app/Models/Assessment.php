<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Assessment extends Model
{
    use HasFactory;

    protected $casts = [
        'confirmed' => 'boolean',
        'date' => 'date',
    ];

    public function assessmentType()
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function offering()
    {
        return $this->belongsTo(Offering::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function scores()
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function topics()
    {
        return $this->belongsToMany(Topic::class, 'assessment_topic');
    }

    public function confirm()
    {
        $this->confirmed = true;
        $this->save();
    }

    public function isConfirmed()
    {
        return $this->confirmed;
    }

    public function delete()
    {
        $this->scores()->delete();
        return parent::delete();
    }
}
