<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * subjects:merge rewrites real school data (every FK from a duplicate subject),
 * so its behavior is pinned here: dry-run touches nothing, --apply re-points
 * assessments, dedupes the subject_term_offering pivot, and deletes the source.
 */
class MergeSubjectsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function duplicatePair(): array
    {
        $school = School::first() ?? School::factory()->create();
        $type = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);

        $source = Subject::factory()->create(['name' => 'S.E.S']);
        $target = Subject::factory()->create(['name' => 'S.E.S.']);

        $assessment = Assessment::factory()->create([
            'assessment_type_id' => $type->id, 'offering_id' => $offering->id,
            'term_id' => $this->term->id, 'subject_id' => $source->id, 'max_score' => 100,
        ]);

        // Both subjects attached to the same (term, offering): the merge must
        // drop the source's pivot row instead of violating the composite key.
        DB::table('subject_term_offering')->insert([
            ['subject_id' => $source->id, 'term_id' => $this->term->id, 'offering_id' => $offering->id],
            ['subject_id' => $target->id, 'term_id' => $this->term->id, 'offering_id' => $offering->id],
        ]);

        return [$source, $target, $assessment, $offering];
    }

    #[Test]
    public function dry_run_reports_but_changes_nothing(): void
    {
        [$source, $target, $assessment] = $this->duplicatePair();

        $this->artisan('subjects:merge', ['source' => $source->id, 'target' => $target->id])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseHas('subjects', ['id' => $source->id]);
        $this->assertSame($source->id, $assessment->fresh()->subject_id);
    }

    #[Test]
    public function apply_repoints_references_dedupes_the_pivot_and_deletes_the_source(): void
    {
        [$source, $target, $assessment, $offering] = $this->duplicatePair();

        $this->artisan('subjects:merge', ['source' => $source->id, 'target' => $target->id, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame($target->id, $assessment->fresh()->subject_id);
        $this->assertDatabaseMissing('subjects', ['id' => $source->id]);

        // Exactly one pivot row remains for the (term, offering), owned by the target.
        $pivot = DB::table('subject_term_offering')
            ->where('term_id', $this->term->id)->where('offering_id', $offering->id)->get();
        $this->assertCount(1, $pivot);
        $this->assertSame($target->id, (int) $pivot[0]->subject_id);
    }

    #[Test]
    public function it_refuses_same_or_missing_subjects(): void
    {
        $subject = Subject::factory()->create();

        $this->artisan('subjects:merge', ['source' => $subject->id, 'target' => $subject->id])->assertExitCode(1);
        $this->artisan('subjects:merge', ['source' => 999999, 'target' => $subject->id])->assertExitCode(1);
    }
}
