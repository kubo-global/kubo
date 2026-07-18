<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\WoundCareVisit;
use App\Models\WoundCase;
use Illuminate\Http\Request;

class WoundCaseController extends Controller
{
    /** The wound-case worklist lives in the health desk now; keep the old URL working. */
    public function index()
    {
        return redirect()->route('health.index', ['view' => 'wounds']);
    }

    public function create(Student $student)
    {
        return view('pages.health.wound.form', [
            'student' => $student,
            'case' => new WoundCase(['opened_on' => now()->toDateString()]),
        ]);
    }

    public function store(Request $request)
    {
        $studentId = $request->input('student');
        $data = $request->validate([
            'opened_on' => 'required|date',
            'diagnosis' => 'required|string',
            'closed_on' => 'nullable|date|after_or_equal:opened_on',
        ]);
        $data['user_id'] = $studentId;
        $case = WoundCase::create($data);

        // First visit is what the paper card calls the initial treatment row.
        if ($request->filled('first_visit_treatment')) {
            $request->validate([
                'first_visit_treatment' => 'string',
                'first_visit_remarks' => 'nullable|string',
            ]);
            $case->visits()->create([
                'visited_on' => $data['opened_on'],
                'treatment' => $request->input('first_visit_treatment'),
                'remarks' => $request->input('first_visit_remarks'),
                'recorded_by' => auth()->id(),
            ]);
        }

        return redirect(route('health.pupil', $studentId))
            ->with('success', 'Wound case opened.');
    }

    public function edit(WoundCase $case)
    {
        return view('pages.health.wound.form', [
            'student' => $case->student,
            'case' => $case->load('visits'),
        ]);
    }

    public function update(Request $request, WoundCase $case)
    {
        $case->update($request->validate([
            'opened_on' => 'required|date',
            'diagnosis' => 'required|string',
            'closed_on' => 'nullable|date|after_or_equal:opened_on',
        ]));

        return redirect(route('health.pupil', $case->user_id))
            ->with('success', 'Wound case updated.');
    }

    public function close(WoundCase $case)
    {
        if ($case->isOpen()) {
            $case->update(['closed_on' => now()->toDateString()]);
        }

        return redirect(route('health.pupil', $case->user_id))
            ->with('success', 'Wound case closed.');
    }

    public function destroy(WoundCase $case)
    {
        $studentId = $case->user_id;
        $case->delete();

        return redirect(route('health.pupil', $studentId))
            ->with('success', 'Wound case deleted.');
    }

    public function addVisit(Request $request, WoundCase $case)
    {
        $data = $request->validate([
            'visited_on' => 'required|date',
            'treatment' => 'required|string',
            'remarks' => 'nullable|string',
        ]);
        $data['recorded_by'] = auth()->id();
        $case->visits()->create($data);

        return redirect(route('health.wound-cases.edit', $case))
            ->with('success', 'Visit recorded.');
    }

    public function updateVisit(Request $request, WoundCareVisit $visit)
    {
        $data = $request->validate([
            'visited_on' => 'required|date',
            'treatment' => 'required|string',
            'remarks' => 'nullable|string',
        ]);
        $visit->update($data);

        return redirect(route('health.wound-cases.edit', $visit->wound_case_id))
            ->with('success', 'Visit updated.');
    }

    public function destroyVisit(WoundCareVisit $visit)
    {
        $caseId = $visit->wound_case_id;
        $visit->delete();

        return redirect(route('health.wound-cases.edit', $caseId))
            ->with('success', 'Visit deleted.');
    }
}
