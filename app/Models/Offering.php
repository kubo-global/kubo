<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Offering extends Model
{
    use HasFactory;
    
    public function schoolyear()
    {
        return $this->belongsTo(Schoolyear::class, 'schoolyear_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    /**
     * Human-readable label for the class. Combines the grade name with
     * the optional offering name (section), e.g. "Grade 1" or
     * "Grade 1 — B" for schools that run multiple sections per grade.
     */
    public function displayName(): string
    {
        $grade = $this->grade->name ?? 'Unknown';
        return $this->name ? "{$grade} - {$this->name}" : $grade;
    }

    /** Badge style for this class, shaded by its section index within the grade. */
    public function badgeStyle(): string
    {
        return $this->grade?->sectionColorStyle((int) ($this->section_index ?? 0))
            ?? 'background-color:#f3f4f6;color:#4b5563;';
    }

    /**
     * Tag each offering in a collection with its 0-based section index within its grade
     * (ordered by name), so class badges can shade sections distinctly without N+1 queries.
     */
    public static function tagSectionIndex($offerings)
    {
        foreach ($offerings->groupBy('grade_id') as $group) {
            foreach ($group->sortBy('name')->values() as $i => $offering) {
                $offering->setAttribute('section_index', $i);
            }
        }

        return $offerings;
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany('App\Models\User', 'enrollments');
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_offering', 'offering_id', 'user_id')->withPivot('principal');
    }

    /**
     * The class principal (homeroom teacher) for this offering. Reports
     * use this to print the responsible teacher's name. A French/PE/etc.
     * teacher assigned to the offering as a subject teacher won't qualify.
     */
    public function principal()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_offering', 'offering_id', 'user_id')
            ->withPivot('principal')
            ->wherePivot('principal', true);
    }

    public function subjectTeachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_offering', 'offering_id', 'user_id')
            ->withPivot('principal')
            ->wherePivot('principal', false);
    }

    public function lessonPlans()
    {
        return $this->hasMany(LessonPlan::class);
    }

    public function subjectAssignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /**
     * Teachers assigned to a specific subject in this class. Zero, one,
     * or more — class teacher + specialist co-teaching is common.
     * Empty collection means the subject is open to any class-attached
     * teacher (the generalist default).
     */
    public function teachersForSubject(int $subjectId): \Illuminate\Support\Collection
    {
        $userIds = TeacherAssignment::where('offering_id', $this->id)
            ->where('subject_id', $subjectId)
            ->pluck('user_id');
        return $userIds->isEmpty()
            ? collect()
            : Teacher::whereIn('id', $userIds)->get();
    }

    /**
     * Topic IDs covered by lesson plans for this offering up to today.
     * Used to gate which Kolibri-mapped exercises are visible to students
     * when `gate_exercises_by_lesson_plan` is enabled school-wide.
     */
    public function coveredTopicIds(): \Illuminate\Support\Collection
    {
        return LessonPlan::where('offering_id', $this->id)
            ->whereNotNull('curriculum_topic_id')
            ->whereDate('lesson_date', '<=', now())
            ->pluck('curriculum_topic_id')
            ->unique()
            ->values();
    }

    public function subjects(int|null|Term $term = null)
    {
        if(is_null($term)) {
            $term = Term::current();
        }

        if (is_int($term)) {
            $term = Term::find($term);
        }
        
        $relation = $this->belongsToMany(Subject::class, 'subject_term_offering', 'offering_id');

        // The per-class/term "counts toward total" override is additive: a database
        // that predates its migration (e.g. the production backup, or a not-yet-migrated
        // deploy) must still resolve subjects, falling back to the subject's school-wide
        // default (Subject::countsTowardTotalResolved). Only pull the pivot column when
        // it actually exists, so withPivot never selects a missing column.
        if (static::subjectTermOfferingHasColumn('counts_toward_total', $this->getConnectionName())) {
            $relation->withPivot('counts_toward_total');
        }
        if (static::subjectTermOfferingHasColumn('sort_order', $this->getConnectionName())) {
            $relation->withPivot('sort_order');
        }
        if (static::subjectTermOfferingHasColumn('core', $this->getConnectionName())) {
            $relation->withPivot('core');
        }

        return $relation->wherePivot("term_id", $term->id);
    }

    /** Cached per connection — hasColumn hits information_schema, and subjects() runs per pupil. */
    private static array $pivotColumnsByConnection = [];

    private static function subjectTermOfferingHasColumn(string $column, ?string $connection): bool
    {
        $connection = $connection ?? config('database.default');
        $key = $connection.'.'.$column;

        if (! array_key_exists($key, static::$pivotColumnsByConnection)) {
            static::$pivotColumnsByConnection[$key] = \Illuminate\Support\Facades\Schema::connection($connection)
                ->hasColumn('subject_term_offering', $column);
        }

        return static::$pivotColumnsByConnection[$key];
    }

    public function toggle()
    {
        $this->activated = !$this->activated;
    }

    public function scopeSchoolyear($query, $schoolyear)
    {
        return $query->where('schoolyear_id', $schoolyear->id);
    }

    public function scopeInSchoolyear($query, $schoolyearId)
    {
        return $query->where('schoolyear_id', $schoolyearId);
    }

    public function enrolledFor($schoolyearId, $gradeId)
    {
        $offering = Offering::where('schoolyear_id', $schoolyearId)
            ->where('grade_id', $gradeId)
            ->with('enrollments')
            ->first();

        return $offering->enrollments;
    }
}
