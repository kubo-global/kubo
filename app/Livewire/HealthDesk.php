<?php

namespace App\Livewire;

use App\Models\HealthReport;
use App\Models\IncidentReport;
use App\Models\MedicalNote;
use App\Models\Schoolyear;
use App\Models\Student;
use App\Models\WoundCase;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The single entry point for health staff. It answers the two questions a
 * caregiver actually has: "who needs following up today?" and "a child is in
 * front of me, where do I record this?" — hence the pupil search at the top and
 * the follow-up worklist below it. The per-type lists (checkups, incidents,
 * wound cases) are views within this screen, not separate destinations.
 */
class HealthDesk extends Component
{
    /** follow-up | checkups | incidents | wounds */
    #[Url]
    public string $view = 'follow-up';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['follow-up', 'checkups', 'incidents', 'wounds'], true)
            ? $view
            : 'follow-up';
    }

    public function closeIncident(IncidentReport $incident): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        if ($incident->isOpen()) {
            $incident->update(['closed_on' => now()->toDateString()]);
        }
    }

    public function closeWound(WoundCase $case): void
    {
        abort_unless(auth()->user()?->can('view medical records'), 403);

        if ($case->isOpen()) {
            $case->update(['closed_on' => now()->toDateString()]);
        }
    }

    /** Pupils matching the search box, so a caregiver can go straight to a record. */
    private function pupils(string $term): Collection
    {
        if ($term === '') {
            return collect();
        }

        return Student::listing()
            ->enrolledIn(Schoolyear::latest())
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'LIKE', "%{$term}%")
                  ->orWhere('last_name', 'LIKE', "%{$term}%");
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    /** Open incidents and open wound cases in one list, longest-waiting first. */
    private function followUps(string $term): Collection
    {
        // ->toBase(): these map to plain rows, not models, so they must not stay
        // Eloquent collections (whose merge() keys on the model).
        $incidents = IncidentReport::with('student')->whereNull('closed_on')->get()
            ->map(fn ($i) => [
                'type' => 'incident',
                'model' => $i,
                'pupil' => $i->student,
                'since' => $i->occurred_at,
                'what' => $i->complaint,
            ])->toBase();

        $wounds = WoundCase::with('student')->withCount('visits')->whereNull('closed_on')->get()
            ->map(fn ($c) => [
                'type' => 'wound',
                'model' => $c,
                'pupil' => $c->student,
                'since' => Carbon::parse($c->opened_on),
                'what' => $c->diagnosis,
            ])->toBase();

        return $incidents->merge($wounds)
            ->filter(fn ($row) => $this->matchesPupil($row['pupil'], $term))
            ->sortBy('since')
            ->values();
    }

    /** The most recent entries of every type, so the desk shows a pulse. */
    private function recent(string $term): Collection
    {
        $entries = collect()
            ->merge(HealthReport::with('user')->latest()->limit(10)->get()
                ->map(fn ($r) => ['type' => 'checkup', 'model' => $r, 'pupil' => $r->user, 'when' => $r->created_at, 'what' => $r->general_condition])->toBase())
            ->merge(IncidentReport::with('student')->latest('occurred_at')->limit(10)->get()
                ->map(fn ($i) => ['type' => 'incident', 'model' => $i, 'pupil' => $i->student, 'when' => $i->occurred_at, 'what' => $i->complaint])->toBase())
            ->merge(WoundCase::with('student')->latest('opened_on')->limit(10)->get()
                ->map(fn ($c) => ['type' => 'wound', 'model' => $c, 'pupil' => $c->student, 'when' => Carbon::parse($c->opened_on), 'what' => $c->diagnosis])->toBase())
            ->merge(MedicalNote::with('student')->latest('noted_on')->limit(10)->get()
                ->map(fn ($n) => ['type' => 'note', 'model' => $n, 'pupil' => $n->student, 'when' => Carbon::parse($n->noted_on), 'what' => $n->content])->toBase());

        return $entries
            ->filter(fn ($row) => $this->matchesPupil($row['pupil'], $term))
            ->sortByDesc('when')
            ->take(12)
            ->values();
    }

    private function matchesPupil(?object $pupil, string $term): bool
    {
        if ($term === '' || !$pupil) {
            return $term === '';
        }

        return stripos($pupil->first_name.' '.$pupil->last_name, $term) !== false;
    }

    public function render()
    {
        $term = trim($this->search);

        $data = [
            'pupils' => $this->pupils($term),
            'followUps' => $this->followUps($term),
            'openCount' => IncidentReport::whereNull('closed_on')->count()
                + WoundCase::whereNull('closed_on')->count(),
            'recent' => collect(),
            'checkups' => collect(),
            'incidents' => collect(),
            'wounds' => collect(),
        ];

        $like = '%'.$term.'%';

        $data['recent'] = $this->view === 'follow-up' ? $this->recent($term) : collect();

        if ($this->view === 'checkups') {
            // Searchable by pupil name or by class, as the old checkup list was.
            $data['checkups'] = HealthReport::listing()
                ->when($term !== '', fn ($q) => $q->where(function ($q) use ($like) {
                    $q->where('users.first_name', 'LIKE', $like)
                      ->orWhere('users.last_name', 'LIKE', $like)
                      ->orWhereExists(function ($sub) use ($like) {
                          $sub->selectRaw('1')
                              ->from('schoolyears')
                              ->join('offerings', 'offerings.schoolyear_id', '=', 'schoolyears.id')
                              ->join('enrollments', 'enrollments.offering_id', '=', 'offerings.id')
                              ->join('grades', 'grades.id', '=', 'offerings.grade_id')
                              ->whereColumn('enrollments.user_id', 'health_reports.user_id')
                              ->whereColumn('schoolyears.start', '<=', 'health_reports.created_at')
                              ->whereColumn('schoolyears.end', '>=', 'health_reports.created_at')
                              ->where('grades.name', 'LIKE', $like);
                      });
                }))
                ->limit(100)->get();
        }

        if ($this->view === 'incidents') {
            $data['incidents'] = IncidentReport::with('student')
                ->when($term !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s
                    ->where('first_name', 'LIKE', $like)->orWhere('last_name', 'LIKE', $like)))
                ->orderByRaw('closed_on is null desc')
                ->orderByDesc('occurred_at')
                ->limit(100)->get();
        }

        if ($this->view === 'wounds') {
            $data['wounds'] = WoundCase::with('student')->withCount('visits')
                ->when($term !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s
                    ->where('first_name', 'LIKE', $like)->orWhere('last_name', 'LIKE', $like)))
                ->orderByRaw('closed_on is null desc')
                ->orderByDesc('opened_on')
                ->limit(100)->get();
        }

        return view('livewire.health.desk', $data);
    }
}
