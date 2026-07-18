<?php

namespace App\Livewire;

use App\Models\HealthReport;
use App\Models\IncidentReport;
use App\Models\MedicalNote;
use App\Models\StudentHealthMilestone;
use App\Models\User;
use App\Models\WoundCase;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The pupil's health record: growth charts, timeline, and the entry form itself.
 * The form is part of this component on purpose — recording a checkup or an
 * incident used to mean leaving the page for a separate form and being thrown
 * back afterwards. Now the panel opens in place and the new entry appears in the
 * timeline underneath it.
 */
class PupilHealth extends Component
{
    public User $user;

    /** null = no panel open. Otherwise: checkup | incident | wound | note | follow-up */
    public ?string $type = null;

    /** Set when the panel is editing an existing entry rather than creating one. */
    public ?int $editingId = null;

    /** The incident a follow-up is being written for. */
    public ?int $followUpFor = null;

    // Shared
    public ?string $when = null;
    public ?string $location = null;
    public ?string $temperature = null;

    // Checkup
    public ?string $generalCondition = null;
    public ?string $height = null;
    public ?string $weight = null;
    public ?string $teeth = null;
    public ?string $eyes = null;
    public ?string $ears = null;
    public ?string $hair = null;
    public ?string $nails = null;
    public ?string $woundObservations = null;
    public ?string $wormTreatment = null;
    public array $milestones = [];

    // Incident
    public ?string $complaint = null;
    public ?string $actionTaken = null;
    public ?string $medicationGiven = null;
    public bool $firstAidGiven = false;
    public bool $parentsContacted = false;
    public bool $sentHome = false;
    public bool $takenToHospital = false;
    public bool $needsFollowUp = false;

    // Wound case
    public ?string $diagnosis = null;
    public ?string $firstVisitTreatment = null;

    // Medical note
    public ?string $content = null;

    public const TYPES = ['checkup', 'incident', 'wound', 'note'];

    public const LABELS = [
        'checkup' => 'Routine checkup',
        'incident' => 'Incident',
        'wound' => 'Wound case',
        'note' => 'Medical note',
    ];

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        $this->user = $user;
    }

    /** Open a blank panel for a new entry of this type. */
    public function start(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $this->resetForm();
        $this->type = $type;
        $this->when = $type === 'incident'
            ? now()->format('Y-m-d\TH:i')
            : now()->toDateString();
    }

    /** Open the panel on an existing entry. Wound cases keep their own page (visits). */
    public function edit(string $type, int $id): void
    {
        abort_unless(in_array($type, ['checkup', 'incident', 'note'], true), 404);

        $this->resetForm();
        $this->type = $type;
        $this->editingId = $id;

        if ($type === 'checkup') {
            $r = HealthReport::where('user_id', $this->user->id)->findOrFail($id);
            $this->generalCondition = $r->general_condition;
            $this->height = $r->height_in_cm;
            $this->weight = $r->weight_kg;
            $this->teeth = $r->teeth_condition;
            $this->eyes = $r->eyes_condition;
            $this->ears = $r->ears_condition;
            $this->hair = $r->hair_condition;
            $this->nails = $r->nails_condition;
            $this->woundObservations = $r->wound_and_bruise_observations;
            $this->wormTreatment = $r->worm_treatment_received === null ? null : (string) (int) $r->worm_treatment_received;
        }

        if ($type === 'incident') {
            $i = IncidentReport::where('user_id', $this->user->id)->findOrFail($id);
            $this->when = $i->occurred_at?->format('Y-m-d\TH:i');
            $this->location = $i->location;
            $this->temperature = $i->temperature;
            $this->complaint = $i->complaint;
            $this->actionTaken = $i->action_taken;
            $this->medicationGiven = $i->medication_given;
            $this->firstAidGiven = (bool) $i->first_aid_given;
            $this->parentsContacted = (bool) $i->parents_contacted;
            $this->sentHome = (bool) $i->sent_home;
            $this->takenToHospital = (bool) $i->taken_to_hospital;
            $this->needsFollowUp = $i->isOpen();
        }

        if ($type === 'note') {
            $n = MedicalNote::where('user_id', $this->user->id)->findOrFail($id);
            $this->when = Carbon::parse($n->noted_on)->toDateString();
            $this->location = $n->location;
            $this->temperature = $n->temperature;
            $this->content = $n->content;
        }
    }

    /** Write a follow-up on an open incident: "checked back, elbow is fine". */
    public function followUp(int $incidentId): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        IncidentReport::where('user_id', $this->user->id)->findOrFail($incidentId);

        $this->resetForm();
        $this->type = 'follow-up';
        $this->followUpFor = $incidentId;
        $this->when = now()->toDateString();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    /** Close an open incident straight from its timeline card, as on the desk. */
    public function closeIncident(int $id): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        $incident = IncidentReport::where('user_id', $this->user->id)->findOrFail($id);
        if ($incident->isOpen()) {
            $incident->update(['closed_on' => now()->toDateString()]);
            session()->flash('health-saved', 'Incident closed.');
        }
    }

    /** Same for a wound case. Its visits are still added on the case itself. */
    public function closeWound(int $id): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        $case = WoundCase::where('user_id', $this->user->id)->findOrFail($id);
        if ($case->isOpen()) {
            $case->update(['closed_on' => now()->toDateString()]);
            session()->flash('health-saved', 'Wound case closed.');
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        $this->validate($this->rules());

        match ($this->type) {
            'checkup' => $this->saveCheckup(),
            'incident' => $this->saveIncident(),
            'wound' => $this->saveWound(),
            'note' => $this->saveNote(),
            'follow-up' => $this->saveFollowUp(),
            default => abort(404),
        };

        $label = self::LABELS[$this->type] ?? 'Follow-up';
        $this->resetForm();
        session()->flash('health-saved', $label.' saved.');
    }

    private function rules(): array
    {
        $condition = 'nullable|in:Poor,Good,Excellent';

        return match ($this->type) {
            'checkup' => [
                'generalCondition' => 'nullable|string|max:191',
                'woundObservations' => 'nullable|string|max:191',
                'height' => 'nullable|integer|between:40,220',
                'weight' => 'nullable|numeric|between:3,150',
                'teeth' => $condition,
                'eyes' => $condition,
                'ears' => $condition,
                'hair' => $condition,
                'nails' => $condition,
                'wormTreatment' => 'nullable|in:0,1',
            ],
            'incident' => [
                'when' => 'required|date',
                'location' => 'nullable|string|max:191',
                'temperature' => 'nullable|numeric|between:30,45',
                'complaint' => 'required|string',
                'actionTaken' => 'nullable|string',
                'medicationGiven' => 'nullable|string|max:191',
            ],
            'wound' => [
                'when' => 'required|date',
                'diagnosis' => 'required|string',
                'firstVisitTreatment' => 'nullable|string',
            ],
            'note' => [
                'when' => 'required|date',
                'content' => 'required|string',
                'location' => 'nullable|string|max:191',
                'temperature' => 'nullable|numeric|between:30,45',
            ],
            'follow-up' => [
                'when' => 'required|date',
                'content' => 'required|string',
            ],
            default => [],
        };
    }

    private function saveFollowUp(): void
    {
        $incident = IncidentReport::where('user_id', $this->user->id)->findOrFail($this->followUpFor);

        $incident->followUps()->create([
            'noted_on' => $this->when,
            'note' => $this->content,
            'recorded_by' => auth()->id(),
        ]);
    }

    private function saveCheckup(): void
    {
        $data = [
            'general_condition' => $this->generalCondition,
            'height_in_cm' => $this->height ?: null,
            'weight_kg' => $this->weight ?: null,
            'teeth_condition' => $this->teeth,
            'eyes_condition' => $this->eyes,
            'ears_condition' => $this->ears,
            'hair_condition' => $this->hair,
            'nails_condition' => $this->nails,
            'wound_and_bruise_observations' => $this->woundObservations,
            'worm_treatment_received' => $this->wormTreatment === null || $this->wormTreatment === ''
                ? null
                : (int) $this->wormTreatment,
        ];

        if ($this->editingId) {
            HealthReport::where('user_id', $this->user->id)->findOrFail($this->editingId)->update($data);

            return;
        }

        HealthReport::create($data + ['user_id' => $this->user->id]);
        $this->promoteMilestones();
    }

    /**
     * Once-true facts (a vaccine, a first period) live on their own row, dated
     * today. Only ever set when missing, and never from an edit of a past report.
     */
    private function promoteMilestones(): void
    {
        $columns = [
            'menstruated' => 'first_menstruated_on',
            'hep-a-vax' => 'hep_a_received_on',
            'polio-vax' => 'polio_received_on',
            'tetanus-vax' => 'tetanus_received_on',
            'yellow-fever-vax' => 'yellow_fever_received_on',
        ];

        $milestone = StudentHealthMilestone::firstOrNew(['user_id' => $this->user->id]);
        $changed = false;
        foreach ($columns as $key => $column) {
            if (!empty($this->milestones[$key]) && !$milestone->$column) {
                $milestone->$column = now()->toDateString();
                $changed = true;
            }
        }

        // Don't create an empty row for a checkup that ticked nothing: it would
        // show an all-"not recorded" milestone block on the record for no reason.
        if ($changed) {
            $milestone->save();
        }
    }

    private function saveIncident(): void
    {
        $data = [
            'occurred_at' => $this->when,
            'location' => $this->location,
            'temperature' => $this->temperature ?: null,
            'complaint' => $this->complaint,
            'action_taken' => $this->actionTaken,
            'medication_given' => $this->medicationGiven,
            'first_aid_given' => $this->firstAidGiven,
            'parents_contacted' => $this->parentsContacted,
            'sent_home' => $this->sentHome,
            'taken_to_hospital' => $this->takenToHospital,
        ];

        if ($this->editingId) {
            $incident = IncidentReport::where('user_id', $this->user->id)->findOrFail($this->editingId);
            $data['closed_on'] = $this->needsFollowUp
                ? null
                : ($incident->closed_on?->toDateString() ?? now()->toDateString());
            $incident->update($data);

            return;
        }

        // Most incidents are done the moment they're logged; only the ones ticked
        // as needing follow-up stay on the worklist.
        $data['closed_on'] = $this->needsFollowUp ? null : now()->toDateString();

        IncidentReport::create($data + [
            'user_id' => $this->user->id,
            'recorded_by' => auth()->id(),
        ]);
    }

    private function saveWound(): void
    {
        $case = WoundCase::create([
            'user_id' => $this->user->id,
            'opened_on' => $this->when,
            'diagnosis' => $this->diagnosis,
        ]);

        if (filled($this->firstVisitTreatment)) {
            $case->visits()->create([
                'visited_on' => $this->when,
                'treatment' => $this->firstVisitTreatment,
                'recorded_by' => auth()->id(),
            ]);
        }
    }

    private function saveNote(): void
    {
        $data = [
            'noted_on' => $this->when,
            'content' => $this->content,
            'location' => $this->location,
            'temperature' => $this->temperature ?: null,
        ];

        if ($this->editingId) {
            MedicalNote::where('user_id', $this->user->id)->findOrFail($this->editingId)->update($data);

            return;
        }

        MedicalNote::create($data + [
            'user_id' => $this->user->id,
            'recorded_by' => auth()->id(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'type', 'editingId', 'followUpFor', 'when', 'location', 'temperature',
            'generalCondition', 'height', 'weight', 'teeth', 'eyes', 'ears', 'hair', 'nails',
            'woundObservations', 'wormTreatment', 'milestones',
            'complaint', 'actionTaken', 'medicationGiven', 'firstAidGiven', 'parentsContacted',
            'sentHome', 'takenToHospital', 'needsFollowUp',
            'diagnosis', 'firstVisitTreatment', 'content',
        ]);
        $this->resetValidation();
    }

    /** Checkups, incidents, wound cases and notes as one chronological list. */
    private function timeline(): Collection
    {
        $user = $this->user;

        return collect()
            ->merge($user->healthReports()->latest()->get()
                ->map(fn ($r) => ['type' => 'checkup', 'when' => $r->created_at, 'model' => $r])->toBase())
            ->merge($user->incidentReports()->with('followUps')->get()
                ->map(fn ($i) => ['type' => 'incident', 'when' => $i->occurred_at, 'model' => $i])->toBase())
            ->merge($user->medicalNotes()->get()
                ->map(fn ($n) => ['type' => 'note', 'when' => Carbon::parse($n->noted_on), 'model' => $n])->toBase())
            ->merge($user->woundCases()->with('visits')->get()
                ->map(fn ($c) => ['type' => 'wound', 'when' => Carbon::parse($c->opened_on), 'model' => $c])->toBase())
            ->sortByDesc('when')
            ->values();
    }

    public function render()
    {
        return view('livewire.user.pupil-health', [
            'entries' => $this->timeline(),
            'milestone' => $this->user->healthMilestone,
        ]);
    }
}
