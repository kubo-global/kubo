<?php

namespace App\Support;

use App\Models\Schoolyear;
use App\Models\Term;
use Carbon\Carbon;

class SchoolCalendar
{
    /**
     * Get the current teaching week number of the school year.
     * Only counts weeks that fall within term dates — vacations are skipped.
     */
    public static function currentSchoolWeek(): int
    {
        $schoolyear = Schoolyear::orderByDesc('id')->first();

        if (!$schoolyear) {
            return 36; // No school year configured — unlock everything
        }

        $terms = Term::where('schoolyear_id', $schoolyear->id)
            ->orderBy('start')
            ->get();

        if ($terms->isEmpty()) {
            return 36;
        }

        $now = Carbon::now();
        $teachingWeek = 0;

        foreach ($terms as $term) {
            $termStart = Carbon::parse($term->start);
            $termEnd = Carbon::parse($term->end);

            if ($now->lt($termStart)) {
                break;
            }

            if ($now->gte($termEnd)) {
                $teachingWeek += (int) $termStart->diffInWeeks($termEnd);
            } else {
                $teachingWeek += (int) $termStart->diffInWeeks($now);
                break;
            }
        }

        return max(1, $teachingWeek + 1);
    }
}
