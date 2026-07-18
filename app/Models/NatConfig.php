<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

/**
 * Per-(school, school year) National Assessment Test setup. Which grades sit it,
 * the subjects + max per grade, the label. Resolved per year so it can change from
 * one year to the next; carried forward on rollover.
 */
class NatConfig extends Model
{
    use HasFactory;

    protected $casts = ['enabled' => 'boolean'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear()
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function subjects()
    {
        return $this->hasMany(NatConfigSubject::class)->orderBy('display_order');
    }

    /** The config for a school + year, or null. */
    public static function for(School $school, Schoolyear $schoolyear): ?self
    {
        return self::where('school_id', $school->id)
            ->where('schoolyear_id', $schoolyear->id)
            ->with('subjects')
            ->first();
    }

    /** Grade ids that sit the NAT this year. */
    public function gradeIds(): Collection
    {
        return $this->subjects->pluck('grade_id')->unique()->values();
    }

    /** Does the given grade sit the NAT in this config's year? */
    public function gradeSits(int $gradeId): bool
    {
        return $this->enabled && $this->gradeIds()->contains($gradeId);
    }

    /** The subject rows (subject_id + max_score) for one grade, in order. */
    public function subjectsForGrade(int $gradeId): Collection
    {
        return $this->subjects->where('grade_id', $gradeId)->values();
    }

    /**
     * Copy this config (and its subject rows) to another school year, unless that
     * year already has one. Returns the target year's config. Used by rollover so a
     * new year starts from last year's setup instead of blank.
     */
    public function copyTo(Schoolyear $target): self
    {
        $existing = self::for($this->school, $target);
        if ($existing) {
            return $existing;
        }

        $copy = self::create([
            'school_id' => $this->school_id,
            'schoolyear_id' => $target->id,
            'enabled' => $this->enabled,
            'label' => $this->label,
        ]);

        foreach ($this->subjects as $s) {
            $copy->subjects()->create([
                'grade_id' => $s->grade_id,
                'subject_id' => $s->subject_id,
                'max_score' => $s->max_score,
                'display_order' => $s->display_order,
            ]);
        }

        return $copy;
    }
}
