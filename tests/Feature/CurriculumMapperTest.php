<?php

namespace Tests\Feature;

use App\Domain\Learning\Models\Skill;
use App\Livewire\CurriculumMapper;
use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use KuboKolibri\Models\CurriculumMap;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurriculumMapperTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Subject $math;

    protected Grade $grade;

    protected Skill $counting;

    protected Skill $adding;

    public function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->math = Subject::create(['name' => 'Mathematics', 'school_id' => $this->school->id]);
        $this->grade = Grade::factory()->create(['name' => 'Grade 1', 'school_id' => $this->school->id]);

        // A skill that already has one exercise (practice) and one video (teach).
        $this->counting = Skill::create([
            'school_id' => $this->school->id,
            'subject_id' => $this->math->id,
            'grade_id' => $this->grade->id,
            'name' => 'Counting',
            'level' => 1,
        ]);
        $this->attachMap($this->counting, 'exercise', 'practice');
        $this->attachMap($this->counting, 'video', 'teach');

        // A skill with no content yet — the mapper must still show it (a visible gap).
        $this->adding = Skill::create([
            'school_id' => $this->school->id,
            'subject_id' => $this->math->id,
            'grade_id' => $this->grade->id,
            'name' => 'Adding',
            'level' => 2,
        ]);
    }

    protected function attachMap(Skill $skill, string $kind, string $role): CurriculumMap
    {
        $map = CurriculumMap::create([
            'school_id' => $this->school->id,
            'subject_id' => $this->math->id,
            'kolibri_channel_id' => fake()->uuid(),
            'kolibri_node_id' => fake()->uuid(),
            'content_kind' => $kind,
            'title' => ucfirst($kind).' item',
        ]);

        DB::table('skill_content')->insert([
            'skill_id' => $skill->id,
            'curriculum_map_id' => $map->id,
            'role' => $role,
            'approved' => true,
        ]);

        return $map;
    }

    #[Test]
    public function the_page_is_admin_only(): void
    {
        $this->actingAs($this->headmaster)->get('/content')->assertOk();
        $this->actingAs($this->admin)->get('/content')->assertOk();
        $this->actingAs($this->teacher)->get('/content')->assertForbidden();
    }

    #[Test]
    public function it_lists_every_skill_and_splits_content_by_kind(): void
    {
        Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->assertSet('subjectId', $this->math->id)
            ->assertSee('Counting')
            ->assertSee('Adding')          // the empty skill still shows
            ->assertSee('no content')
            ->call('selectSkill', $this->counting->id)
            ->assertViewHas('exercises', fn ($e) => $e->count() === 1)
            ->assertViewHas('videos', fn ($v) => $v->count() === 1);
    }

    #[Test]
    public function the_skill_list_counts_only_active_content(): void
    {
        // Set the counting exercise aside; it must no longer count as active,
        // but show up as "set aside" instead.
        $ex = $this->counting->content()->where('content_kind', 'exercise')->first();
        DB::table('skill_content')
            ->where('skill_id', $this->counting->id)
            ->where('curriculum_map_id', $ex->id)
            ->update(['approved' => false]);

        Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->assertSee('1 vid')
            ->assertSee('1 set aside')
            ->assertDontSee('1 ex');
    }

    #[Test]
    public function attaching_an_exercise_maps_it_as_practice(): void
    {
        $nodeId = fake()->uuid();

        Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->call('selectSkill', $this->adding->id)
            ->set('results', [[
                'id' => $nodeId,
                'title' => 'Count to ten',
                'kind' => 'exercise',
                'channel_id' => fake()->uuid(),
                'content_id' => fake()->uuid(),
            ]])
            ->call('attach', $nodeId);

        $map = CurriculumMap::where('kolibri_node_id', $nodeId)->first();
        $this->assertNotNull($map);
        $this->assertSame('exercise', $map->content_kind);
        $this->assertDatabaseHas('skill_content', [
            'skill_id' => $this->adding->id,
            'curriculum_map_id' => $map->id,
            'role' => 'practice',
        ]);
    }

    #[Test]
    public function attaching_a_video_maps_it_as_teach(): void
    {
        $nodeId = fake()->uuid();

        Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->call('selectSkill', $this->adding->id)
            ->set('results', [[
                'id' => $nodeId,
                'title' => 'Counting song',
                'kind' => 'video',
                'channel_id' => fake()->uuid(),
                'content_id' => fake()->uuid(),
            ]])
            ->call('attach', $nodeId);

        $map = CurriculumMap::where('kolibri_node_id', $nodeId)->first();
        $this->assertSame('video', $map->content_kind);
        $this->assertDatabaseHas('skill_content', [
            'skill_id' => $this->adding->id,
            'curriculum_map_id' => $map->id,
            'role' => 'teach',
        ]);
    }

    #[Test]
    public function it_records_a_remark_on_a_mapping(): void
    {
        $map = $this->counting->content()->where('content_kind', 'exercise')->first();

        Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->call('selectSkill', $this->counting->id)
            ->call('editNote', $map->id)
            ->set('noteDraft', 'uses the American unit system')
            ->call('saveNote', $map->id)
            ->assertSet('editingNoteFor', null);

        $this->assertDatabaseHas('curriculum_maps', [
            'id' => $map->id,
            'note' => 'uses the American unit system',
        ]);
    }

    #[Test]
    public function setting_aside_maps_it_inactive_and_opens_the_remark(): void
    {
        $nodeId = fake()->uuid();

        $component = Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->call('selectSkill', $this->adding->id)
            ->set('results', [[
                'id' => $nodeId,
                'title' => 'Miles and pounds',
                'kind' => 'exercise',
                'channel_id' => fake()->uuid(),
                'content_id' => fake()->uuid(),
            ]])
            ->call('attach', $nodeId, false);

        $map = CurriculumMap::where('kolibri_node_id', $nodeId)->first();

        // Mapped against the skill but not shown to pupils, with the note editor open.
        $this->assertDatabaseHas('skill_content', [
            'skill_id' => $this->adding->id,
            'curriculum_map_id' => $map->id,
            'approved' => false,
        ]);
        $component->assertSet('editingNoteFor', $map->id);
    }

    #[Test]
    public function it_detaches_and_toggles_approval(): void
    {
        $map = $this->counting->content()->where('content_kind', 'exercise')->first();

        $component = Livewire::actingAs($this->headmaster)
            ->test(CurriculumMapper::class)
            ->call('selectSkill', $this->counting->id);

        // Hide from pupils.
        $component->call('toggleApproval', $map->id, false);
        $this->assertDatabaseHas('skill_content', [
            'skill_id' => $this->counting->id,
            'curriculum_map_id' => $map->id,
            'approved' => false,
        ]);

        // Remove entirely.
        $component->call('detach', $map->id);
        $this->assertDatabaseMissing('skill_content', [
            'skill_id' => $this->counting->id,
            'curriculum_map_id' => $map->id,
        ]);
    }
}
