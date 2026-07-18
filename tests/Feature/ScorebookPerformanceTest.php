<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScorebookPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function classWith(int $studentCount): Offering
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);

        foreach (range(1, $studentCount) as $i) {
            $student = Student::factory()->create();
            Enrollment::factory()->create(['user_id' => $student->id, 'offering_id' => $offering->id]);
        }

        return $offering;
    }

    #[Test]
    public function the_class_page_query_count_is_independent_of_class_size(): void
    {
        School::factory()->create();

        $smallClass = $this->classWith(3);
        $bigClass = $this->classWith(18);

        $queriesFor = function (Offering $offering): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->headmaster)->get(route('scorebook.class', $offering))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        // Warm per-request caches (Spatie permissions, config) so the first measured
        // request isn't penalised for one-time setup queries.
        $this->actingAs($this->headmaster)->get(route('scorebook.class', $smallClass))->assertOk();

        $small = $queriesFor($smallClass);
        $big = $queriesFor($bigClass);

        $this->assertSame(
            $small,
            $big,
            "Scorebook class page ran {$small} queries for 3 students but {$big} for 18 — a query that scales with class size is an N+1."
        );
    }
}
