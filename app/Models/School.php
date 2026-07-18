<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory;

    public function configs()
    {
        return $this->hasMany(SchoolConfig::class);
    }

    public function assessmentTypes()
    {
        return $this->hasMany(AssessmentType::class)->orderBy('display_order');
    }

    public function gradingScales()
    {
        return $this->hasMany(GradingScale::class)->orderBy('display_order');
    }

    public function config(string $key, $default = null)
    {
        $config = $this->configs()->where('key', $key)->first();

        return $config ? $config->value : $default;
    }
}
