<?php

namespace App\Models;

use App\Exceptions\AssessmentScoreIsNullAndNotAbsent;
use App\Exceptions\AssessmentScoreIsNotNullAndAbsent;
use App\Exceptions\AssessmentScoreHigherThanMaxScore;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssessmentScore extends Model
{
    use HasFactory;

    /**
     * The table has a composite primary key (user_id, assessment_id) and no `id`
     * column. Eloquent otherwise assumes an auto-incrementing `id`, so the UPDATE
     * path (updateOrCreate on an existing score) keys on a non-existent `id` — a
     * 500 on MySQL, a silent no-op on SQLite. Declaring the key correctly and
     * keying save/delete/refresh queries on both columns fixes score corrections.
     */
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('user_id', $this->getOriginal('user_id', $this->user_id))
            ->where('assessment_id', $this->getOriginal('assessment_id', $this->assessment_id));
    }

    protected function setKeysForSelectQuery($query)
    {
        return $this->setKeysForSaveQuery($query);
    }

    public static function boot()
    {
        parent::boot();

        self::saving(function ($assessmentScore) {
            if ($assessmentScore->score === null && $assessmentScore->absent === false) {
                throw new AssessmentScoreIsNullAndNotAbsent();
            }
            if ($assessmentScore->score !== null && $assessmentScore->absent === true) {
                throw new AssessmentScoreIsNotNullAndAbsent();
            }
            if ($assessmentScore->score > $assessmentScore->assessment->max_score) {
                throw new AssessmentScoreHigherThanMaxScore();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }
}
