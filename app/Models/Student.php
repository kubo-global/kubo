<?php

namespace App\Models;

use App\Models\Schoolyear;
use Facade\Ignition\QueryRecorder\Query;

class Student extends User
{

    protected $table = 'users';
    protected $guard_name = 'web';

    public function getForeignKey()
    {
        return 'user_id';
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function offerings()
    {
        return $this->belongsToMany(Offering::class, 'enrollments');
    }

    public function healthReports()
    {
        return $this->hasMany(HealthReport::class);
    }

    public function incidentReports()
    {
        return $this->hasMany(IncidentReport::class, 'user_id');
    }

    public function woundCases()
    {
        return $this->hasMany(WoundCase::class, 'user_id');
    }

    public function medicalNotes()
    {
        return $this->hasMany(MedicalNote::class, 'user_id');
    }

    public function scopeListing($query)
    {
        return $query
            ->select(
                'users.*',
                'schoolyears.name as schoolyear_name',
                'schoolyears.id as schoolyear_id',
                'grades.name as grade_name',
                'grades.id as grade_id'
            )
            ->leftJoin('enrollments', 'users.id', '=', 'enrollments.user_id')
            ->leftJoin('offerings', 'enrollments.offering_id', 'offerings.id')
            ->leftJoin('grades', 'offerings.grade_id', 'grades.id')
            ->leftJoin('schoolyears', 'offerings.schoolyear_id', 'schoolyears.id');
    }

    public function scopeEnrolledIn($query, Schoolyear $schoolyear){
        return $query->where('schoolyear_id', '=', $schoolyear->id);
    }

    // Restrict to pupils in a class this teacher is assigned to. Uses whereExists
    // rather than a join so a class with several teachers doesn't multiply the
    // pupil rows (which is why the list showed each pupil once per class teacher).
    public function scopeWithTeacher($query, User $user){
        return $query->whereExists(function ($q) use ($user) {
            $q->selectRaw('1')
              ->from('teacher_offering')
              ->whereColumn('teacher_offering.offering_id', 'offerings.id')
              ->where('teacher_offering.user_id', $user->id);
        });
    }
}
