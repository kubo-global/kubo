<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\HealthReport;
use App\Models\Student;
use App\Models\StudentHealthMilestone;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('pages.health.index');
    }

    /** A pupil's whole health record: growth charts, timeline, and the entry forms. */
    public function pupil(Student $student)
    {
        return view('pages.health.pupil', ['student' => $student]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Student $student)
    {
        return view('pages.health.create', [
            'student' => $student,
            'healthReport' => null,
            'milestone' => $student->healthMilestone ?? new StudentHealthMilestone(['user_id' => $student->id]),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate($this->rules() + [
            'student' => 'required|exists:users,id',
        ]);
        $studentId = $request->input('student');

        HealthReport::create([
            'general_condition' => $request->input('general-condition'),
            'height_in_cm' => $request->input('height'),
            'weight_kg' => $request->input('weight'),
            'teeth_condition' => $request->input('teeth-condition'),
            'eyes_condition' => $request->input('eye-condition'),
            'ears_condition' => $request->input('ear-condition'),
            'hair_condition' => $request->input('hair-condition'),
            'nails_condition' => $request->input('nail-condition'),
            'wound_and_bruise_observations' => $request->input('wounds-bruises'),
            'worm_treatment_received' => $request->input('worm-treatment-received'),
            'user_id' => $studentId,
        ]);

        // Promote any newly recorded once-true milestones onto the dedicated
        // milestone row so the form doesn't ask again next visit. Only set
        // a date if the milestone is currently missing.
        $milestone = StudentHealthMilestone::firstOrNew(['user_id' => $studentId]);
        $today = now()->toDateString();
        $fields = [
            'menstruated' => 'first_menstruated_on',
            'hep-a-vax' => 'hep_a_received_on',
            'polio-vax' => 'polio_received_on',
            'tetanus-vax' => 'tetanus_received_on',
            'yellow-fever-vax' => 'yellow_fever_received_on',
        ];
        foreach ($fields as $input => $column) {
            if ($request->input($input) && !$milestone->$column) {
                $milestone->$column = $today;
            }
        }
        $milestone->save();

        // Land on the student's profile, Health tab — the chart there shows
        // the report we just created in context with their history, much
        // more useful than the bare /health listing.
        return redirect(route('health.pupil', $studentId))
            ->with('success', 'Health report saved.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(HealthReport $healthReport)
    {
        return view('pages.health.show', [
            'healthReport' => $healthReport,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HealthReport $healthReport)
    {
        return view('pages.health.create', [
            'student' => $healthReport->user,
            'healthReport' => $healthReport,
            // Milestones are intentionally not editable here. Editing a past
            // report shouldn't retroactively set milestone dates to today.
            'milestone' => null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HealthReport $healthReport)
    {
        $request->validate($this->rules());

        $healthReport->update([
            'general_condition' => $request->input('general-condition'),
            'height_in_cm' => $request->input('height'),
            'weight_kg' => $request->input('weight'),
            'teeth_condition' => $request->input('teeth-condition'),
            'eyes_condition' => $request->input('eye-condition'),
            'ears_condition' => $request->input('ear-condition'),
            'hair_condition' => $request->input('hair-condition'),
            'nails_condition' => $request->input('nail-condition'),
            'wound_and_bruise_observations' => $request->input('wounds-bruises'),
            'worm_treatment_received' => $request->input('worm-treatment-received'),
        ]);

        return redirect()->route('health.show', $healthReport);
    }

    /**
     * The bounds mirror the form's own min/max and select options, so a typo
     * (a height of 1300, a weight typed in grams, a made-up condition) is
     * caught instead of quietly ending up in the growth chart.
     */
    private function rules(): array
    {
        $condition = 'nullable|in:Poor,Good,Excellent';

        return [
            'general-condition' => 'nullable|string|max:191',
            'wounds-bruises' => 'nullable|string|max:191',
            'height' => 'nullable|integer|between:40,220',
            'weight' => 'nullable|numeric|between:3,150',
            'teeth-condition' => $condition,
            'eye-condition' => $condition,
            'ear-condition' => $condition,
            'hair-condition' => $condition,
            'nail-condition' => $condition,
            'worm-treatment-received' => 'nullable|boolean',
            'menstruated' => 'nullable|boolean',
            'hep-a-vax' => 'nullable|boolean',
            'polio-vax' => 'nullable|boolean',
            'tetanus-vax' => 'nullable|boolean',
            'yellow-fever-vax' => 'nullable|boolean',
        ];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
