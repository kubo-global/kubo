<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
use App\Models\Student;
use Illuminate\Http\Request;

class IncidentReportController extends Controller
{
    /** The incident log lives in the health desk now; keep the old URL working. */
    public function index()
    {
        return redirect()->route('health.index', ['view' => 'incidents']);
    }

    public function create(Student $student)
    {
        return view('pages.health.incident.form', [
            'student' => $student,
            'incident' => new IncidentReport(['occurred_at' => now()]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->input('student');
        $data['recorded_by'] = auth()->id();
        // Most incidents are done the moment they're logged. Only the ones ticked
        // as needing follow-up stay open on the worklist.
        $data['closed_on'] = $request->boolean('needs_follow_up') ? null : now()->toDateString();
        IncidentReport::create($data);

        return redirect(route('health.pupil', $data['user_id']))
            ->with('success', 'Incident recorded.');
    }

    public function edit(IncidentReport $incident)
    {
        return view('pages.health.incident.form', [
            'student' => $incident->student,
            'incident' => $incident,
        ]);
    }

    public function update(Request $request, IncidentReport $incident)
    {
        $data = $this->validated($request);
        // Re-opening clears the closing date; closing keeps the one it already had.
        $data['closed_on'] = $request->boolean('needs_follow_up')
            ? null
            : ($incident->closed_on?->toDateString() ?? now()->toDateString());
        $incident->update($data);

        return redirect(route('health.pupil', $incident->user_id))
            ->with('success', 'Incident updated.');
    }

    public function close(IncidentReport $incident)
    {
        if ($incident->isOpen()) {
            $incident->update(['closed_on' => now()->toDateString()]);
        }

        return redirect(route('health.pupil', $incident->user_id))
            ->with('success', 'Incident closed.');
    }

    public function destroy(IncidentReport $incident)
    {
        $studentId = $incident->user_id;
        $incident->delete();

        return redirect(route('health.pupil', $studentId))
            ->with('success', 'Incident deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'occurred_at' => 'required|date',
            'location' => 'nullable|string|max:191',
            'temperature' => 'nullable|numeric|between:30,45',
            'complaint' => 'required|string',
            'action_taken' => 'nullable|string',
            'first_aid_given' => 'nullable|boolean',
            'medication_given' => 'nullable|string|max:191',
            'parents_contacted' => 'nullable|boolean',
            'sent_home' => 'nullable|boolean',
            'taken_to_hospital' => 'nullable|boolean',
        ]);
    }
}
