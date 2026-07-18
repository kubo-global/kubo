<?php

namespace App\Livewire;

use App\Domain\Learning\Models\Skill;
use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use KuboKolibri\Client\KolibriClient;
use KuboKolibri\Models\CurriculumMap;
use Livewire\Component;

/**
 * The exercise & video mapper: manage, in the UI, the curriculum-to-Kolibri
 * mapping that used to live only in a hand-edited JSON fixture.
 *
 * Pick a grade and subject, walk the skill graph in teaching order (gaps
 * included, so you can see what still needs content), and per skill attach
 * Kolibri exercises (as practice) and videos (as teaching resources), remove
 * them, or hide one from pupils. Every write lands in the same curriculum_maps
 * + skill_content tables the installer fills, so a fixture install and hand
 * edits coexist.
 */
class CurriculumMapper extends Component
{
    public ?int $subjectId = null;

    public ?int $gradeId = null;

    public ?int $selectedSkillId = null;

    public string $search = '';

    /** @var array<int, array{id:string,title:string,kind:string,channel_id:?string,content_id:?string}> */
    public array $results = [];

    public bool $searched = false;

    public ?string $kolibriError = null;

    public ?int $editingNoteFor = null;

    public string $noteDraft = '';

    public function mount(): void
    {
        $school = School::first();

        // Default to the subject that actually carries mapped skills (Mathematics
        // in the bundled curriculum), falling back to the first subject with skills.
        $this->subjectId = Subject::where('school_id', $school?->id)
            ->whereIn('id', Skill::query()->select('subject_id'))
            ->orderByRaw("name = 'Mathematics' desc")
            ->orderBy('name')
            ->value('id');

        $this->gradeId = $this->firstGradeForSubject();
    }

    public function updatedSubjectId(): void
    {
        $this->gradeId = $this->firstGradeForSubject();
        $this->selectedSkillId = null;
        $this->resetSearch();
    }

    public function updatedGradeId(): void
    {
        $this->selectedSkillId = null;
        $this->resetSearch();
    }

    public function selectSkill(int $skillId): void
    {
        $this->selectedSkillId = $skillId;
        $this->resetSearch();
    }

    public function runSearch(KolibriClient $client): void
    {
        $query = trim($this->search);
        $this->searched = true;
        $this->kolibriError = null;

        if (mb_strlen($query) < 2) {
            $this->results = [];

            return;
        }

        try {
            $this->results = $client->searchContent($query)
                ->filter(fn ($node) => in_array($node['kind'] ?? '', ['exercise', 'video'], true))
                ->map(fn ($node) => [
                    'id' => $node['id'],
                    'title' => $node['title'] ?? 'Untitled',
                    'kind' => $node['kind'],
                    'channel_id' => $node['channel_id'] ?? null,
                    'content_id' => $node['content_id'] ?? null,
                ])
                ->take(30)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $this->results = [];
            $this->kolibriError = 'Could not reach the content server. Check that Kolibri is running.';
        }
    }

    /**
     * Attach a Kolibri item to the selected skill. Set $active = false to map it
     * "set aside": recorded against the skill but not shown to pupils — the way to
     * tell the next person "this was considered, don't use it" (add the reason as
     * a remark). The note editor opens straight away for a set-aside item.
     */
    public function attach(string $nodeId, bool $active = true): void
    {
        $skill = $this->selectedSkillId ? Skill::find($this->selectedSkillId) : null;
        $item = collect($this->results)->firstWhere('id', $nodeId);
        if (! $skill || ! $item) {
            return;
        }

        // Exercises are practice; videos (and other resources) are watch-first teaching aids.
        $role = $item['kind'] === 'exercise' ? 'practice' : 'teach';
        $school = School::first();

        $map = CurriculumMap::updateOrCreate(
            ['school_id' => $school->id, 'kolibri_node_id' => $item['id']],
            [
                'subject_id' => $skill->subject_id,
                'kolibri_channel_id' => $item['channel_id'] ?? '',
                'kolibri_content_id' => $item['content_id'],
                'content_kind' => $item['kind'],
                'title' => $item['title'],
                'mapped_by' => auth()->id(),
            ],
        );

        DB::table('skill_content')->updateOrInsert(
            ['skill_id' => $skill->id, 'curriculum_map_id' => $map->id],
            ['role' => $role, 'approved' => $active],
        );

        if ($active) {
            session()->flash('mapper', "Added \"{$item['title']}\" to {$skill->name}.");
        } else {
            session()->flash('mapper', "Set \"{$item['title']}\" aside. Add a reason so the next person knows why.");
            $this->editNote($map->id);
        }
    }

    public function detach(int $mapId): void
    {
        if (! $this->selectedSkillId) {
            return;
        }

        DB::table('skill_content')
            ->where('skill_id', $this->selectedSkillId)
            ->where('curriculum_map_id', $mapId)
            ->delete();
    }

    public function toggleApproval(int $mapId, bool $approved): void
    {
        if (! $this->selectedSkillId) {
            return;
        }

        DB::table('skill_content')
            ->where('skill_id', $this->selectedSkillId)
            ->where('curriculum_map_id', $mapId)
            ->update(['approved' => $approved]);
    }

    public function editNote(int $mapId): void
    {
        $this->editingNoteFor = $mapId;
        $this->noteDraft = (string) CurriculumMap::whereKey($mapId)->value('note');
    }

    public function saveNote(int $mapId): void
    {
        // The note is a property of the content, so it applies wherever this map
        // is used — not only under the currently selected skill.
        CurriculumMap::whereKey($mapId)->update(['note' => trim($this->noteDraft) ?: null]);
        $this->cancelNote();
    }

    public function cancelNote(): void
    {
        $this->editingNoteFor = null;
        $this->noteDraft = '';
    }

    public function render()
    {
        $school = School::first();

        $subjects = Subject::where('school_id', $school?->id)
            ->whereIn('id', Skill::query()->select('subject_id'))
            ->orderBy('name')
            ->get();

        $grades = Grade::where('school_id', $school?->id)
            ->whereIn('id', Skill::where('subject_id', $this->subjectId)->whereNotNull('grade_id')->select('grade_id'))
            ->orderBy('id')
            ->get();

        $skills = Skill::with(['content' => fn ($q) => $q->orderBy('display_order')->orderBy('title')])
            ->where('subject_id', $this->subjectId)
            ->where('grade_id', $this->gradeId)
            ->orderBy('level')
            ->orderBy('scheduled_week')
            ->orderBy('id')
            ->get();

        $selected = $this->selectedSkillId ? $skills->firstWhere('id', $this->selectedSkillId) : null;

        $exercises = $selected ? $selected->content->where('content_kind', 'exercise')->values() : collect();
        $videos = $selected ? $selected->content->where('content_kind', '!=', 'exercise')->values() : collect();
        $mappedNodeIds = $selected ? $selected->content->pluck('kolibri_node_id')->all() : [];
        $setAsideNodeIds = $selected
            ? $selected->content->reject(fn ($m) => $m->pivot->approved)->pluck('kolibri_node_id')->all()
            : [];

        return view('livewire.curriculum-mapper', [
            'subjects' => $subjects,
            'grades' => $grades,
            'skills' => $skills,
            'selected' => $selected,
            'exercises' => $exercises,
            'videos' => $videos,
            'mappedNodeIds' => $mappedNodeIds,
            'setAsideNodeIds' => $setAsideNodeIds,
        ]);
    }

    private function firstGradeForSubject(): ?int
    {
        return Skill::where('subject_id', $this->subjectId)
            ->whereNotNull('grade_id')
            ->orderBy('grade_id')
            ->value('grade_id');
    }

    private function resetSearch(): void
    {
        $this->search = '';
        $this->results = [];
        $this->searched = false;
        $this->kolibriError = null;
    }
}
