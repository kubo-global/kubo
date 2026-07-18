<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schoolyear extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $casts = ['start' => 'datetime', 'end' => 'datetime'];
    protected $guarded = [];

    public static function current() : ?Schoolyear
    {
        return Schoolyear::where('start', '<=', Carbon::now())
            ->where('end', '>=', Carbon::now())
            ->first();
    }

    public static function latest(): ?Schoolyear
    {
        return Schoolyear::orderBy('start', 'desc')->first();
    }

    public function offerings() : HasMany
    {
        return $this->hasMany(Offering::class);
    }

    /**
     * Fetching the current schoolyear will be based on a session variable.
     * By default the schoolyear will fallback on the current schoolyear based on a date,
     * this is in case that the session variable is not set.
     *
     * @return Schoolyear
     */
    public static function selected() : Schoolyear
    {
        if (session()->has('schoolyear')) {
            return session('schoolyear');
        } else {
            return Schoolyear::latest();
        }
    }

    public function terms() : HasMany
    {
        return $this->hasMany(Term::class);
    }
}
