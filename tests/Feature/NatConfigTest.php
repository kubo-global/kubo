<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\NatConfig;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NatConfigTest extends TestCase
{
    use RefreshDatabase;

    private function configFor(School $school, Schoolyear $year, Grade $grade, array $subjectNames, int $max = 100): NatConfig
    {
        $config = NatConfig::create([
            'school_id' => $school->id,
            'schoolyear_id' => $year->id,
            'enabled' => true,
            'label' => 'National Assessment Test',
        ]);

        foreach ($subjectNames as $i => $name) {
            $subject = Subject::factory()->create(['name' => $name]);
            $config->subjects()->create([
                'grade_id' => $grade->id,
                'subject_id' => $subject->id,
                'max_score' => $max,
                'display_order' => $i,
            ]);
        }

        return $config->fresh('subjects');
    }

    #[Test]
    public function it_resolves_the_config_for_a_school_year(): void
    {
        $school = School::factory()->create();
        $year = Schoolyear::factory()->create();
        $grade = Grade::factory()->create();
        $this->configFor($school, $year, $grade, ['English', 'Maths', 'Integrated Studies']);

        $config = NatConfig::for($school, $year);

        $this->assertNotNull($config);
        $this->assertTrue($config->gradeSits($grade->id));
        $this->assertCount(3, $config->subjectsForGrade($grade->id));
        // a grade not in the config does not sit it
        $this->assertFalse($config->gradeSits(Grade::factory()->create()->id));
    }

    #[Test]
    public function a_disabled_config_means_no_grade_sits_it(): void
    {
        $school = School::factory()->create();
        $year = Schoolyear::factory()->create();
        $grade = Grade::factory()->create();
        $config = $this->configFor($school, $year, $grade, ['English']);
        $config->update(['enabled' => false]);

        $this->assertFalse($config->fresh('subjects')->gradeSits($grade->id));
    }

    #[Test]
    public function it_returns_null_when_a_year_has_no_config(): void
    {
        $school = School::factory()->create();
        $this->assertNull(NatConfig::for($school, Schoolyear::factory()->create()));
    }

    #[Test]
    public function carry_forward_copies_last_years_setup_to_a_new_year(): void
    {
        $school = School::factory()->create();
        $lastYear = Schoolyear::factory()->create();
        $newYear = Schoolyear::factory()->create();
        $grade = Grade::factory()->create();
        $last = $this->configFor($school, $lastYear, $grade, ['English', 'Maths'], max: 100);

        $copy = $last->copyTo($newYear);

        $this->assertNotEquals($last->id, $copy->id);
        $this->assertSame($newYear->id, $copy->schoolyear_id);
        $this->assertCount(2, $copy->fresh('subjects')->subjectsForGrade($grade->id));
        // copying again is idempotent (returns the existing one, no duplicates)
        $again = $last->copyTo($newYear);
        $this->assertSame($copy->id, $again->id);
        $this->assertSame(1, NatConfig::where('schoolyear_id', $newYear->id)->count());
    }
}
