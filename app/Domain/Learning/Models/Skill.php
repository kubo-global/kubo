<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use KuboKolibri\Models\CurriculumMap;

class Skill extends Model
{
    protected $guarded = [];

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    public function subject()
    {
        return $this->belongsTo(\App\Models\Subject::class);
    }

    public function grade()
    {
        return $this->belongsTo(\App\Models\Grade::class);
    }

    /**
     * Skills that this skill requires (prerequisites).
     * "Area of rectangle" requires "Multiplication"
     */
    public function prerequisites()
    {
        return $this->belongsToMany(self::class, 'skill_edges', 'skill_id', 'prerequisite_id');
    }

    /**
     * Skills that require this skill (dependents).
     * "Multiplication" is required by "Area of rectangle"
     */
    public function dependents()
    {
        return $this->belongsToMany(self::class, 'skill_edges', 'prerequisite_id', 'skill_id');
    }

    /**
     * Kolibri content mapped to this skill.
     */
    public function content()
    {
        return $this->belongsToMany(CurriculumMap::class, 'skill_content')
            ->withPivot('role', 'approved');
    }

    public function practiceContent()
    {
        return $this->content()->wherePivot('role', 'practice')->wherePivot('approved', true);
    }

    /**
     * All practice content including unapproved — for the content browser.
     */
    public function allPracticeContent()
    {
        return $this->content()->wherePivot('role', 'practice');
    }

    public function teachContent()
    {
        return $this->content()->wherePivot('role', 'teach');
    }

    /**
     * Student mastery records for this skill.
     */
    public function studentSkills()
    {
        return $this->hasMany(StudentSkill::class);
    }

    public function exerciseRuns()
    {
        return $this->hasMany(ExerciseRun::class);
    }

    /**
     * Restrict to skills that have at least one approved practice exercise
     * tagged to a covered curriculum topic. Used by the lesson-plan gate
     * on the student-facing /learn surface.
     */
    public function scopeWithCoveredTopic($query, $coveredTopicIds)
    {
        if (empty($coveredTopicIds) || (is_object($coveredTopicIds) && method_exists($coveredTopicIds, 'isEmpty') && $coveredTopicIds->isEmpty())) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('content', function ($q) use ($coveredTopicIds) {
            $q->where('skill_content.role', 'practice')
              ->where('skill_content.approved', true)
              ->whereIn('curriculum_maps.topic_id', $coveredTopicIds);
        });
    }
}
