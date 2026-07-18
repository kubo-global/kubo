<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolloverController extends Controller
{
    /**
     * Step 1: Create a new school year.
     */
    public function index()
    {
        $previousYear = Schoolyear::latest();
        $years = Schoolyear::orderByDesc('start')->get();

        return view('pages.rollover.create', [
            'previousYear' => $previousYear,
            'years' => $years,
        ]);
    }

    /**
     * Step 1 POST: Create school year + terms + offerings.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'term1_start' => 'required|date',
            'term1_end' => 'required|date',
            'term2_start' => 'required|date',
            'term2_end' => 'required|date',
            'term3_start' => 'required|date',
            'term3_end' => 'required|date',
            'copy_from' => 'nullable|exists:schoolyears,id',
        ]);

        $schoolyear = Schoolyear::create([
            'name' => $validated['name'],
            'start' => $validated['start'],
            'end' => $validated['end'],
        ]);

        // Create 3 terms
        foreach (['1', '2', '3'] as $i) {
            Term::create([
                'name' => "Term {$i}",
                'start' => $validated["term{$i}_start"],
                'end' => $validated["term{$i}_end"],
                'schoolyear_id' => $schoolyear->id,
            ]);
        }

        // Create one offering per grade
        $grades = Grade::orderBy('id')->get();
        foreach ($grades as $grade) {
            $offering = Offering::create([
                'schoolyear_id' => $schoolyear->id,
                'grade_id' => $grade->id,
                'name' => '',
                'activated' => true,
            ]);

            // Copy teacher assignments and subject mappings from previous year
            if ($validated['copy_from']) {
                $this->copyFromPreviousYear($offering, $grade, $validated['copy_from']);
            }
        }

        // Carry the NAT configuration forward so the new year starts from last year's
        // setup (which grades sit it + their subjects); editable afterwards.
        if ($validated['copy_from']) {
            $school = \App\Models\School::first();
            $source = Schoolyear::find($validated['copy_from']);
            if ($school && $source) {
                \App\Models\NatConfig::for($school, $source)?->copyTo($schoolyear);
            }
        }

        return redirect()->route('rollover.promote', $schoolyear)
            ->with('success', "School year {$schoolyear->name} created with " . $grades->count() . " classes.");
    }

    /**
     * Step 2: Review and promote students.
     */
    public function promote(Schoolyear $schoolyear)
    {
        $previousYear = Schoolyear::where('id', '<', $schoolyear->id)
            ->orderByDesc('id')
            ->first();

        if (!$previousYear) {
            return redirect()->route('rollover.index')
                ->with('error', 'No previous school year to promote from.');
        }

        $grades = Grade::orderBy('id')->get();
        $gradeOrder = $grades->pluck('id')->toArray();

        // Get previous year's offerings with enrolled students
        $previousOfferings = Offering::where('schoolyear_id', $previousYear->id)
            ->with(['grade', 'students' => fn ($q) => $q->where('archived', false)->orderBy('first_name')])
            ->get()
            ->sortBy(fn ($o) => array_search($o->grade_id, $gradeOrder));

        // Group new year's offerings by grade so we can present a section
        // picker when a grade has more than one class (e.g. Grade 1 A + B).
        $newOfferingsByGrade = Offering::where('schoolyear_id', $schoolyear->id)
            ->with('grade')
            ->get()
            ->groupBy('grade_id');

        // Already enrolled in new year (to show as "done")
        $alreadyEnrolled = Enrollment::whereHas('offering', fn ($q) => $q->where('schoolyear_id', $schoolyear->id))
            ->pluck('user_id')
            ->toArray();

        // Find the highest grade (for graduation)
        $highestGradeId = $grades->last()?->id;

        // Build next-grade map
        $nextGradeMap = [];
        foreach ($grades as $i => $grade) {
            $nextGradeMap[$grade->id] = $grades->get($i + 1)?->id;
        }

        return view('pages.rollover.promote', [
            'schoolyear' => $schoolyear,
            'previousYear' => $previousYear,
            'previousOfferings' => $previousOfferings,
            'newOfferingsByGrade' => $newOfferingsByGrade,
            'alreadyEnrolled' => $alreadyEnrolled,
            'highestGradeId' => $highestGradeId,
            'nextGradeMap' => $nextGradeMap,
            'grades' => $grades,
        ]);
    }

    /**
     * Step 2 POST: Process promotions.
     */
    public function processPromotions(Request $request, Schoolyear $schoolyear)
    {
        $actions = $request->input('action', []);

        $promoted = 0;
        $repeated = 0;
        $left = 0;
        $graduated = 0;

        // Map of offering_id => Offering (only for the target year) for validation.
        $newOfferings = Offering::where('schoolyear_id', $schoolyear->id)->get()->keyBy('id');

        DB::transaction(function () use ($actions, $newOfferings, $schoolyear, &$promoted, &$repeated, &$left, &$graduated) {
            foreach ($actions as $studentId => $action) {
                $student = User::find($studentId);
                if (!$student) continue;

                switch ($action) {
                    case 'promote':
                        $targetOfferingId = (int) request()->input("promote_offering.{$studentId}");
                        $targetOffering = $newOfferings->get($targetOfferingId);
                        if ($targetOffering) {
                            Enrollment::firstOrCreate([
                                'user_id' => $studentId,
                                'offering_id' => $targetOffering->id,
                            ]);
                            $promoted++;
                        }
                        break;

                    case 'repeat':
                        $targetOfferingId = (int) request()->input("repeat_offering.{$studentId}");
                        $targetOffering = $newOfferings->get($targetOfferingId);
                        if ($targetOffering) {
                            Enrollment::firstOrCreate([
                                'user_id' => $studentId,
                                'offering_id' => $targetOffering->id,
                            ]);
                            $repeated++;
                        }
                        break;

                    case 'left':
                        $student->archived = true;
                        $student->save();
                        $left++;
                        break;

                    case 'graduated':
                        $student->archived = true;
                        $student->save();
                        $graduated++;
                        break;
                }
            }
        });

        $message = "{$promoted} promoted, {$repeated} repeating";
        if ($graduated > 0) $message .= ", {$graduated} graduated";
        if ($left > 0) $message .= ", {$left} left school";

        return redirect()->route('rollover.new-students', $schoolyear)
            ->with('success', $message);
    }

    /**
     * Step 3: Enroll new students.
     */
    public function newStudents(Schoolyear $schoolyear)
    {
        $offerings = Offering::where('schoolyear_id', $schoolyear->id)
            ->with('grade')
            ->get()
            ->sortBy('grade_id');

        return view('pages.rollover.new-students', [
            'schoolyear' => $schoolyear,
            'offerings' => $offerings,
        ]);
    }

    /**
     * Step 3 POST: Create and enroll new students.
     */
    public function enrollNewStudent(Request $request, Schoolyear $schoolyear)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'offering_id' => 'required|exists:offerings,id',
            'gender' => 'nullable|string|in:male,female',
            'date_of_birth' => 'nullable|date',
        ]);

        $student = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'password' => bcrypt('student'),
        ]);
        $student->assignRole('student');

        if ($validated['gender'] ?? null) {
            $student->profile()->create([
                'gender' => $validated['gender'],
                'birth_date' => $validated['date_of_birth'] ?? null,
            ]);
        }

        Enrollment::create([
            'user_id' => $student->id,
            'offering_id' => $validated['offering_id'],
        ]);

        return redirect()->route('rollover.new-students', $schoolyear)
            ->with('success', "{$student->first_name} {$student->last_name} enrolled.");
    }

    /**
     * Copy teacher assignments and subject-term mappings from a previous year.
     */
    private function copyFromPreviousYear(Offering $newOffering, Grade $grade, int $previousYearId): void
    {
        $previousOffering = Offering::where('schoolyear_id', $previousYearId)
            ->where('grade_id', $grade->id)
            ->first();

        if (!$previousOffering) return;

        // Copy teacher assignments
        $teacherAssignments = DB::table('teacher_offering')
            ->where('offering_id', $previousOffering->id)
            ->get();

        foreach ($teacherAssignments as $ta) {
            DB::table('teacher_offering')->insertOrIgnore([
                'user_id' => $ta->user_id,
                'offering_id' => $newOffering->id,
                'principal' => $ta->principal,
            ]);
        }

        // Copy subject-term mappings
        // Map old terms to new terms by name
        $oldTerms = Term::where('schoolyear_id', $previousYearId)->pluck('id', 'name');
        $newTerms = Term::where('schoolyear_id', $newOffering->schoolyear_id)->pluck('id', 'name');

        $oldMappings = DB::table('subject_term_offering')
            ->where('offering_id', $previousOffering->id)
            ->get();

        foreach ($oldMappings as $mapping) {
            $oldTermName = $oldTerms->search($mapping->term_id);
            $newTermId = $newTerms->get($oldTermName);

            if ($newTermId) {
                DB::table('subject_term_offering')->insertOrIgnore([
                    'subject_id' => $mapping->subject_id,
                    'term_id' => $newTermId,
                    'offering_id' => $newOffering->id,
                ]);
            }
        }
    }
}
