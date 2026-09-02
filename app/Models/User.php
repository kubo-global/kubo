<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;
    use HasFactory;

    private ?Enrollment $currentEnrollmentCached = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'must_change_password'
    ];

    protected $casts = [
        'must_change_password' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /** Staff-only employment record (PRN, TIN, status, appointment dates). */
    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function healthMilestone()
    {
        return $this->hasOne(StudentHealthMilestone::class);
    }

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_user');
    }

    //Necessary relationship to check if a user is a student or not.
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function currentEnrollment(): ?Enrollment
    {
        if ($this->currentEnrollmentCached !== null) {
            return $this->currentEnrollmentCached;
        }

        $year = Schoolyear::current() ?? Schoolyear::latest();
        if (!$year) {
            return null;
        }

        return $this->currentEnrollmentCached = $this->enrollments()
            ->whereHas('offering', fn ($q) => $q->where('schoolyear_id', $year->id))
            ->with('offering.grade')
            ->first();
    }

    public function currentGradeId(): ?int
    {
        return $this->currentEnrollment()?->offering?->grade_id;
    }

    public function currentOfferingId(): ?int
    {
        return $this->currentEnrollment()?->offering_id;
    }

    public function subjectAssignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /**
     * May this user enter grades for a (class, subject) combination?
     * Rules:
     *   - headmaster / admin / system_admin bypass (oversight roles).
     *   - The class principal bypasses for any subject in their class.
     *   - If a specific subject teacher is assigned to (offering, subject)
     *     via TeacherAssignment, only THAT teacher can grade it. This is
     *     the lock case (e.g. French teacher shielding French grades from
     *     the generalist class teacher).
     *   - If no subject teacher is assigned, any teacher attached to the
     *     class via teacher_offering can grade. This is the default —
     *     primary-school class teachers grade all their class's subjects.
     */
    public function canEnterGradesFor(int $offeringId, int $subjectId): bool
    {
        if ($this->hasAnyRole(['headmaster', 'admin', 'system_admin'])) {
            return true;
        }

        $isPrincipal = \Illuminate\Support\Facades\DB::table('teacher_offering')
            ->where('user_id', $this->id)
            ->where('offering_id', $offeringId)
            ->where('principal', true)
            ->exists();
        if ($isPrincipal) {
            return true;
        }

        $assignedTeacherIds = TeacherAssignment::where('offering_id', $offeringId)
            ->where('subject_id', $subjectId)
            ->pluck('user_id');

        if ($assignedTeacherIds->isNotEmpty()) {
            // Subject is locked to a specific set of teachers (one or more).
            return $assignedTeacherIds->contains($this->id);
        }

        // No subject teacher — generalist access for anyone attached.
        return \Illuminate\Support\Facades\DB::table('teacher_offering')
            ->where('user_id', $this->id)
            ->where('offering_id', $offeringId)
            ->exists();
    }

    //todo: make this a local scope instead of a regular function
    public function getFirstNameAttribute($value)
    {
        return ucfirst($value);
    }

    //todo: make this a local scope instead of a regular function
    public function getLastNameAttribute($value)
    {
        return ucfirst($value);
    }

    //todo: make this a local scope instead of a regular function
    public function getFullNameAttribute()
    {
        return ucfirst($this->first_name) . ' ' . ucfirst($this->last_name);
    }

    //todo: make this a local scope instead of a regular function
    public function getInitials(){
        return substr($this->first_name, 0, 1) . substr($this->last_name,0,1);
    }

    /**
     * Age in whole years, at $at (default: today). Report cards pass the term's
     * end date: a card for Term 3 of 2023-24 must show how old the pupil was
     * then, not how old they are on the day someone reprints it.
     */
    public function getAge(?Carbon $at = null): ?int
    {
        $birthDate = $this->profile?->birth_date;

        if ($birthDate === null) {
            return null;
        }

        return (int) Carbon::parse($birthDate)->diffInYears($at ?? Carbon::now());
    }
}
