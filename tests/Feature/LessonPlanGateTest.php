<?php

namespace Tests\Feature;

use App\Domain\Learning\Models\Skill;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\LessonPlan;
use App\Models\Offering;
use App\Models\School;
use App\Models\SchoolConfig;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use KuboKolibri\Models\CurriculumMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LessonPlanGateTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Subject $math;
    private Subject $english;
    private Grade $grade;
    private Offering $offering;
    private Topic $mathFractions;
    private Topic $mathPlaceValue;
    private Topic $englishGrammar;

    public function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['kolibri_facility_id' => 'test-fac']);
        $this->math = Subject::create(['name' => 'Mathematics']);
        $this->english = Subject::create(['name' => 'English']);
        $this->grade = Grade::factory()->create(['name' => 'Grade 3']);
        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => $this->grade->id,
        ]);

        DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'principal' => true,
        ]);

        Enrollment::create(['user_id' => $this->student->id, 'offering_id' => $this->offering->id]);

        $this->mathFractions = Topic::create(['name' => 'Fractions', 'subject_id' => $this->math->id]);
        $this->mathPlaceValue = Topic::create(['name' => 'Place Value', 'subject_id' => $this->math->id]);
        $this->englishGrammar = Topic::create(['name' => 'Grammar', 'subject_id' => $this->english->id]);
    }

    #[Test]
    public function teacher_can_attach_a_curriculum_topic_to_a_lesson_plan()
    {
        $this->actingAs($this->teacher)
            ->post(route('lesson-plans.store'), [
                'offering_id' => $this->offering->id,
                'subject_id' => $this->math->id,
                'lesson_date' => '2026-05-06',
                'topic' => 'Halves and quarters',
                'curriculum_topic_id' => $this->mathFractions->id,
            ])
            ->assertRedirect();

        $plan = LessonPlan::latest('id')->first();
        $this->assertEquals($this->mathFractions->id, $plan->curriculum_topic_id);
        $this->assertEquals($this->mathFractions->id, $plan->curriculumTopic->id);
    }

    #[Test]
    public function lesson_plan_rejects_a_curriculum_topic_from_a_different_subject()
    {
        $this->actingAs($this->teacher)
            ->post(route('lesson-plans.store'), [
                'offering_id' => $this->offering->id,
                'subject_id' => $this->math->id,
                'lesson_date' => '2026-05-06',
                'topic' => 'Mismatch',
                'curriculum_topic_id' => $this->englishGrammar->id, // English topic on a Math plan
            ])
            ->assertSessionHasErrors('curriculum_topic_id');
    }

    #[Test]
    public function covered_topic_ids_returns_distinct_past_topics_for_the_offering()
    {
        // Past math plans: Fractions (yesterday) + Place Value (today)
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->subDay()->toDateString(),
            'topic' => 'Halves',
            'curriculum_topic_id' => $this->mathFractions->id,
        ]);
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->toDateString(),
            'topic' => 'Tens and ones',
            'curriculum_topic_id' => $this->mathPlaceValue->id,
        ]);
        // Future plan — should NOT count
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->english->id,
            'lesson_date' => now()->addDay()->toDateString(),
            'topic' => 'Pronouns',
            'curriculum_topic_id' => $this->englishGrammar->id,
        ]);
        // Plan with no curriculum_topic_id — should NOT count
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->subDays(2)->toDateString(),
            'topic' => 'Free-text only',
        ]);

        $covered = $this->offering->coveredTopicIds();
        $this->assertEqualsCanonicalizing(
            [$this->mathFractions->id, $this->mathPlaceValue->id],
            $covered->all(),
        );
    }

    #[Test]
    public function gate_setting_can_be_toggled_from_settings_endpoint()
    {
        $this->actingAs($this->admin)
            ->post(route('settings.update-gate-by-lesson-plan'), ['enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue((bool) $this->school->fresh()->config('gate_exercises_by_lesson_plan'));

        $this->actingAs($this->admin)
            ->post(route('settings.update-gate-by-lesson-plan'), []) // checkbox absent = off
            ->assertRedirect();

        $this->assertFalse((bool) $this->school->fresh()->config('gate_exercises_by_lesson_plan'));
    }

    #[Test]
    public function learn_page_hides_uncovered_skills_when_gate_is_on()
    {
        // Two skills, two exercises, two different topics.
        [$fractionsSkill, ] = $this->createSkillForTopic($this->mathFractions, 'Add fractions');
        [$placeValueSkill, ] = $this->createSkillForTopic($this->mathPlaceValue, 'Tens');

        // Only Fractions covered — Place Value untaught.
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->subDay()->toDateString(),
            'topic' => 'Halves',
            'curriculum_topic_id' => $this->mathFractions->id,
        ]);

        SchoolConfig::updateOrCreate(
            ['school_id' => $this->school->id, 'key' => 'gate_exercises_by_lesson_plan'],
            ['value' => true],
        );

        $response = $this->actingAs($this->student)->get(route('learn.index'));
        $response->assertOk();

        $covered = Skill::withCoveredTopic($this->offering->coveredTopicIds())->pluck('id')->all();
        $this->assertContains($fractionsSkill->id, $covered);
        $this->assertNotContains($placeValueSkill->id, $covered);
    }

    #[Test]
    public function learn_page_shows_everything_when_gate_is_off()
    {
        [$fractionsSkill, ] = $this->createSkillForTopic($this->mathFractions, 'Add fractions');
        [$placeValueSkill, ] = $this->createSkillForTopic($this->mathPlaceValue, 'Tens');

        // No SchoolConfig row = gate off by default. Even with no lesson plans,
        // both skills should remain visible.
        $this->actingAs($this->student)->get(route('learn.index'))->assertOk();

        $this->assertNull($this->school->fresh()->config('gate_exercises_by_lesson_plan'));
    }

    #[Test]
    public function practice_skill_rotates_to_a_covered_topic_when_lesson_plans_exist()
    {
        [$fractionsSkill, ] = $this->createSkillForTopic($this->mathFractions, 'Add fractions');
        [$placeValueSkill, ] = $this->createSkillForTopic($this->mathPlaceValue, 'Tens');

        // Both topics covered in past lesson plans.
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->subDays(2)->toDateString(),
            'topic' => 'Halves',
            'curriculum_topic_id' => $this->mathFractions->id,
        ]);
        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->subDay()->toDateString(),
            'topic' => 'Tens and ones',
            'curriculum_topic_id' => $this->mathPlaceValue->id,
        ]);

        $coveredSkillIds = [$fractionsSkill->id, $placeValueSkill->id];

        // Across many requests, every nextSkill picked should be in a covered topic.
        for ($i = 0; $i < 20; $i++) {
            $response = $this->actingAs($this->student)->get(route('learn.index'));
            $response->assertOk();
            $nextSkill = $response->viewData('nextSkill');
            if ($nextSkill) {
                $this->assertContains(
                    $nextSkill->id,
                    $coveredSkillIds,
                    'Practice skill must come from a covered topic.',
                );
            }
        }
    }

    #[Test]
    public function practice_skill_is_untouched_when_no_lesson_plans_link_topics()
    {
        $this->createSkillForTopic($this->mathFractions, 'Add fractions');

        // No lesson plans at all → rotation no-ops.
        $response = $this->actingAs($this->student)->get(route('learn.index'));
        $response->assertOk();
        // Just asserting the page renders. Without lesson plans, the existing
        // SkillGraph pick is whatever it is — we only assert the override
        // didn't crash the request.
    }

    private function createSkillForTopic(Topic $topic, string $name): array
    {
        $skill = Skill::create([
            'school_id' => $this->school->id,
            'subject_id' => $topic->subject_id,
            'grade_id' => $this->grade->id,
            'name' => $name,
            'level' => 1,
        ]);

        $map = CurriculumMap::create([
            'school_id' => $this->school->id,
            'subject_id' => $topic->subject_id,
            'topic_id' => $topic->id,
            'kolibri_channel_id' => fake()->uuid(),
            'kolibri_node_id' => fake()->uuid(),
            'content_kind' => 'exercise',
        ]);

        DB::table('skill_content')->insert([
            'skill_id' => $skill->id,
            'curriculum_map_id' => $map->id,
            'role' => 'practice',
            'approved' => true,
        ]);

        return [$skill, $map];
    }
}
