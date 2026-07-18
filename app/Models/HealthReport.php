<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthReport extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * Weight is only ever shown and entered in kilograms. The column is called
     * weight_in_gram, but the form has always been labelled "kg" and stored the
     * raw kg value, so existing rows are mixed: some hold kg (27), others grams
     * (27000). Anything under 1000 is a kg value. Read and write weight through
     * this accessor so nothing outside the model deals in grams; writes are
     * normalised to grams.
     */
    protected function weightKg(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->weight_in_gram
                ? ($this->weight_in_gram < 1000
                    ? (float) $this->weight_in_gram
                    : $this->weight_in_gram / 1000)
                : null,
            set: fn ($kg) => [
                'weight_in_gram' => ($kg === null || $kg === '')
                    ? null
                    : (int) round((float) $kg * 1000),
            ],
        );
    }

    /**
     * Sub-query that resolves the student's class as of a given moment — i.e. the
     * grade they were enrolled in during the school year that contains the date.
     * Used to show the class a report belonged to (so old reports keep a class
     * even after the student has left).
     */
    public static function classAsOf(string $dateColumn)
    {
        return \App\Models\Schoolyear::query()
            ->join('offerings', 'offerings.schoolyear_id', '=', 'schoolyears.id')
            ->join('enrollments', 'enrollments.offering_id', '=', 'offerings.id')
            ->join('grades', 'grades.id', '=', 'offerings.grade_id')
            ->whereColumn('enrollments.user_id', 'health_reports.user_id')
            ->whereColumn('schoolyears.start', '<=', $dateColumn)
            ->whereColumn('schoolyears.end', '>=', $dateColumn);
    }

    public function scopeListing($query)
    {
        return $query
            ->select('health_reports.*', 'users.first_name', 'users.last_name')
            ->selectSub(
                self::classAsOf('health_reports.created_at')->select('grades.name')->limit(1),
                'grade_name'
            )
            ->leftJoin('users', 'users.id', '=', 'health_reports.user_id')
            ->orderByDesc('health_reports.created_at');
    }
}
