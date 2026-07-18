<?php

namespace App\Livewire;

use App\Domain\Reporting\Services\PositionService;
use App\Domain\Reporting\Services\ReportReadiness;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\ReportRemark;
use App\Models\School;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * "Prepare reports": the moment staff finalize term reports for a class. Pupils
 * are listed in rank order (the same ranking the card prints), with their
 * position/average as context, and per pupil the two things a rubric can't
 * compute — conduct and a general remark — are typed and saved here, then
 * printed on the card in place of a hand-written blank.
 */
class PrepareReports extends Component
{
    public Offering $offering;

    public ?int $termId = null;

    /** Keyed by enrollment id. */
    public array $conduct = [];

    public array $remarks = [];

    public ?int $savedFor = null;

    public function mount(Offering $offering): void
    {
        $this->offering = $offering->load('grade', 'schoolyear', 'principal');

        $terms = $this->terms();
        $requested = (int) request('term');
        $this->termId = ($requested && $terms->contains('id', $requested))
            ? $requested
            : ($this->currentTerm($terms)?->id ?? $terms->first()?->id);

        $this->loadRemarks();
    }

    public function updatedTermId(): void
    {
        // Clamp to this class's own terms; a crafted request can set anything.
        if (! $this->terms()->contains('id', $this->termId)) {
            $this->termId = $this->currentTerm($this->terms())?->id ?? $this->terms()->first()?->id;
        }

        $this->savedFor = null;
        $this->loadRemarks();
    }

    /**
     * Renderless: a blur only upserts the remark. Re-rendering would rebuild the
     * whole class ranking (PositionService walks every pupil's term report) on
     * every field blur, which is far too heavy on a classroom Pi. The "Saved"
     * flash is handled client-side by Alpine on the returned promise.
     */
    #[\Livewire\Attributes\Renderless]
    public function saveRemark(int $enrollmentId): void
    {
        if (! $this->termId) {
            return;
        }

        // Only pupils of this class; the enrollment id arrives from the client.
        abort_unless(
            Enrollment::where('id', $enrollmentId)->where('offering_id', $this->offering->id)->exists(),
            403,
        );

        ReportRemark::updateOrCreate(
            ['enrollment_id' => $enrollmentId, 'term_id' => $this->termId],
            [
                'conduct' => trim((string) ($this->conduct[$enrollmentId] ?? '')) ?: null,
                'general_remarks' => trim((string) ($this->remarks[$enrollmentId] ?? '')) ?: null,
            ],
        );

        $this->savedFor = $enrollmentId;
    }

    public function render()
    {
        $school = School::first();
        $term = $this->termId ? Term::find($this->termId) : null;

        $enrollmentByUser = Enrollment::where('offering_id', $this->offering->id)->pluck('id', 'user_id');

        $rows = collect();
        if ($term) {
            $rows = (new PositionService())->rank($this->offering, $term, $school)
                ->map(fn ($r) => [
                    'student' => $r['student'],
                    'enrollment_id' => $enrollmentByUser[$r['student_id']] ?? null,
                    'position' => $r['position'],
                    'average' => $r['average'],
                    'total' => $r['total'],
                ])
                ->filter(fn ($x) => $x['enrollment_id'])
                ->values();
        }

        $incomplete = $term
            ? (new ReportReadiness())->incompleteSubjects($this->offering, $term, $school)
            : collect();

        return view('livewire.prepare-reports', [
            'terms' => $this->terms(),
            'term' => $term,
            'rows' => $rows,
            'incomplete' => $incomplete,
        ]);
    }

    private function terms()
    {
        return $this->offering->schoolyear?->terms()->orderBy('start')->get() ?? collect();
    }

    private function currentTerm($terms): ?Term
    {
        $now = Carbon::now();

        return $terms->first(fn ($t) => $t->start <= $now && $t->end >= $now);
    }

    private function loadRemarks(): void
    {
        $this->conduct = [];
        $this->remarks = [];

        if (! $this->termId) {
            return;
        }

        $rows = ReportRemark::where('term_id', $this->termId)
            ->whereIn('enrollment_id', Enrollment::where('offering_id', $this->offering->id)->select('id'))
            ->get();

        foreach ($rows as $r) {
            $this->conduct[$r->enrollment_id] = $r->conduct;
            $this->remarks[$r->enrollment_id] = $r->general_remarks;
        }
    }
}
