<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;

class AuthoredQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'hints' => 'array',
        ];
    }

    public function exercise()
    {
        return $this->belongsTo(AuthoredExercise::class, 'authored_exercise_id');
    }

    /**
     * Generate a deterministic assessment item ID from this question's ID.
     */
    public function getAssessmentItemId(): string
    {
        if ($this->assessment_item_id) {
            return $this->assessment_item_id;
        }

        $id = md5("kubo-question-{$this->id}");
        $this->update(['assessment_item_id' => $id]);

        return $id;
    }
}
