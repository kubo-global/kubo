<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['counts_toward_total' => 'boolean'];

    /**
     * Whether this subject counts toward the term total/average (and thus
     * pupil ranking) in the context it was loaded for.
     *
     * When the subject comes off the `subject_term_offering` pivot (i.e. via
     * Offering::subjects()), a non-null pivot `counts_toward_total` overrides
     * the school-wide default for that one class + term; null means inherit.
     * Outside that pivot context (no pivot loaded) the school-wide default
     * applies. The `?? true` on the default keeps a pre-migration database
     * (production backup without the column) counting rather than zeroing
     * every total.
     */
    public function countsTowardTotalResolved(): bool
    {
        $override = $this->pivot?->getAttribute('counts_toward_total');
        if (! is_null($override)) {
            return (bool) $override;
        }

        return (bool) ($this->getAttributes()['counts_toward_total'] ?? true);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function skills()
    {
        return $this->hasMany(\App\Domain\Learning\Models\Skill::class);
    }
}
