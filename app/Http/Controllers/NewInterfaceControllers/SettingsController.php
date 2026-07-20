<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\Offering;
use App\Models\Period;
use App\Models\School;
use App\Models\SchoolConfig;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Modules\Registry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SettingsController extends Controller
{
    /**
     * Redirect back to /settings on a specific tab. POST→redirect strips
     * URL fragments by default, so the Alpine x-data initializer would
     * otherwise bounce the user back to #academic after every action.
     */
    private function backToTab(string $tab): \Illuminate\Http\RedirectResponse
    {
        return redirect(route('settings.index') . '#' . $tab);
    }

    /**
     * Upload the school logo used on report headers (NAT scores/analysis, term
     * reports). Stored under public/logos and referenced by schools.logo_path,
     * so the deliverables are no longer tied to a hard-coded asset.
     */
    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $school = School::firstOrFail();

        $filename = 'school-' . $school->id . '-' . time() . '.' . $request->file('logo')->getClientOriginalExtension();
        $request->file('logo')->move(public_path('logos'), $filename);

        $school->logo_path = 'logos/' . $filename;
        $school->save();

        return $this->backToTab('school');
    }

    public function index()
    {
        $schoolyear = Schoolyear::latest();
        $school = School::first();

        return view('pages.settings.index', [
            'school' => $school,
            'schoolyear' => $schoolyear,
            'schoolyears' => Schoolyear::orderByDesc('start')->get(),
            'grades' => Grade::orderBy('id')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'terms' => $schoolyear ? $schoolyear->terms()->orderBy('start')->get() : collect(),
            'offerings' => $schoolyear
                ? Offering::where('schoolyear_id', $schoolyear->id)->with(['grade', 'teachers'])->get()->sortBy('grade_id')
                : collect(),
            'assessmentTypes' => AssessmentType::orderBy('display_order')->get(),
            'staffStatuses' => \App\Models\StaffStatus::orderBy('display_order')->orderBy('label')->get(),
            'periods' => Period::ordered($school?->id)->get(),
            'reportType' => $school?->config(\App\Models\SchoolConfig::REPORT_TYPE, 'term') ?? 'term',
            'periodMode' => $school?->config(\App\Models\SchoolConfig::SCOREBOOK_PERIOD_MODE, 'months') ?? 'months',
            'termCardLayout' => $school?->config(\App\Models\SchoolConfig::TERM_CARD_LAYOUT, 'default') ?? 'default',
            'gradeBands' => $school ? $school->gradingScales()->whereNull('purpose')->orderBy('grade_min')->orderByDesc('min_score')->get() : collect(),
            'gateByLessonPlan' => (bool) ($school?->config(\App\Models\SchoolConfig::GATE_EXERCISES_BY_LESSON_PLAN, false)),
            'expectedHours' => json_decode((string) ($school?->config(\App\Models\SchoolConfig::EXPECTED_INSTRUCTIONAL_HOURS) ?? ''), true) ?: [],
            'modules' => Registry::definitions(),
            'enabledModules' => Registry::enabledList(),
            'permissionMatrix' => $this->buildPermissionMatrix(),
        ]);
    }

    /**
     * Roles + permissions in display order, plus a {role → [permission, ...]}
     * map of current grants. The student role is excluded — students don't
     * have admin-tunable permissions. Spatie's role IDs are the source of
     * truth; the matrix UI persists changes via syncPermissions().
     */
    private function buildPermissionMatrix(): array
    {
        $rolesOrder = ['headmaster', 'admin', 'system_admin', 'teacher', 'caregiver', 'assistant_coordinator'];

        $roles = Role::all()
            ->whereIn('name', $rolesOrder)
            ->sortBy(fn ($r) => array_search($r->name, $rolesOrder));

        $permissions = Permission::orderBy('name')->get();

        $grants = [];
        foreach ($roles as $role) {
            $grants[$role->name] = $role->permissions->pluck('name')->all();
        }

        return [
            'roles' => $roles->values(),
            'permissions' => $permissions,
            'grants' => $grants,
        ];
    }

    /**
     * Replace the permission grants for a single role with whatever the
     * matrix submitted. Empty checkbox set → role keeps no permissions.
     */
    public function updateRolePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $this->backToTab('permissions')->with('success', "Updated permissions for {$role->name}.");
    }

    public function toggleModule(Request $request, string $slug)
    {
        $enabled = $request->boolean('enabled');
        $defs = Registry::definitions();

        if (!isset($defs[$slug])) {
            return $this->backToTab('modules')->with('error', "Unknown module: {$slug}.");
        }
        if ($defs[$slug]['always_on'] ?? false) {
            return $this->backToTab('modules')->with('error', "{$defs[$slug]['label']} can't be disabled.");
        }

        Registry::setEnabled($slug, $enabled);

        return $this->backToTab('modules')->with('success', $defs[$slug]['label'] . ($enabled ? ' enabled.' : ' disabled.'));
    }

    public function updateGateByLessonPlan(Request $request)
    {
        $enabled = $request->boolean('enabled');
        $school = School::first();

        if (!$school) {
            return $this->backToTab('academic')->with('error', 'No school configured.');
        }

        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::GATE_EXERCISES_BY_LESSON_PLAN],
            ['value' => $enabled],
        );

        return $this->backToTab('academic')->with('success', $enabled
            ? 'Students will now only see exercises for topics covered in a lesson plan.'
            : 'Topic gating disabled — students see all approved exercises.');
    }

    public function updateAssessmentType(Request $request, AssessmentType $assessmentType)
    {
        $validated = $request->validate([
            // Submitted as a percentage (0–100); stored as a 0–1 decimal so
            // downstream report math (score * weight) keeps working.
            'weight_percent' => 'required|integer|min:0|max:100',
            'default_max_score' => 'nullable|integer|min:1|max:1000',
        ]);

        $assessmentType->update([
            'weight' => $validated['weight_percent'] / 100,
            'default_max_score' => $validated['default_max_score'] ?? null,
        ]);

        // Weights that don't sum to 100% silently corrupt every term total (a
        // subject's total is the sum of weighted parts). Warn, don't block — the
        // sum is naturally off while editing one type at a time.
        $sum = (float) AssessmentType::where('school_id', $assessmentType->school_id)->sum('weight');
        if (abs($sum - 1.0) > 0.001) {
            return $this->backToTab('grading')->with(
                'warning',
                sprintf('Updated %s — but the weights now add up to %d%%, not 100%%. Term totals will be wrong until they do.', $assessmentType->name, round($sum * 100)),
            );
        }

        return $this->backToTab('grading')->with('success', "Updated {$assessmentType->name} settings.");
    }

    // ---- Subjects ----

    public function storeSubject(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:100|unique:subjects,name']);
        Subject::create($validated);
        return $this->backToTab('academic')->with('success', "Subject \"{$validated['name']}\" added.");
    }

    /**
     * How the scorebook slices a term: loose calendar months (teacher marks each
     * as Test or Exam) or the fixed Test 1 / Test 2 / Exam rhythm. Exposed here
     * so a school can switch it without anyone needing server access.
     */
    public function updatePeriodMode(Request $request)
    {
        $validated = $request->validate(['period_mode' => 'required|in:months,tests']);
        $school = School::first();
        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::SCOREBOOK_PERIOD_MODE],
            ['value' => $validated['period_mode']],
        );
        $label = $validated['period_mode'] === 'tests' ? 'Test 1 / Test 2 / Exam' : 'calendar months';

        return $this->backToTab('grading')->with('success', "Score entry periods set to: {$label}.");
    }

    /** The one-page term card's layout — schools keep their own printed format. */
    public function updateTermCardLayout(Request $request)
    {
        $validated = $request->validate(['term_card_layout' => 'required|in:default,albreda,swallow']);
        $school = School::first();
        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::TERM_CARD_LAYOUT],
            ['value' => $validated['term_card_layout']],
        );

        return $this->backToTab('grading')->with('success', 'Term card layout set to: '.$validated['term_card_layout'].'.');
    }

    public function updateReportType(Request $request)
    {
        $validated = $request->validate(['report_type' => 'required|in:term,book']);
        $school = School::first();
        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::REPORT_TYPE],
            ['value' => $validated['report_type']],
        );
        $label = $validated['report_type'] === 'book' ? 'Report Book (annual grid)' : 'Term report (one page per term)';
        return $this->backToTab('grading')->with('success', "Report type set to: {$label}.");
    }

    /**
     * Per-school expected instructional hours per weekday (Mon-Fri). Used as the
     * Expected column on the instructional-hours sheet, overriding the timetable
     * calculation. All blank = fall back to the timetable-derived total.
     */
    public function updateInstructionalHours(Request $request)
    {
        $v = $request->validate([
            'hours'   => 'array',
            'hours.*' => 'nullable|numeric|min:0|max:24',
        ]);

        $map = [];
        foreach ([1, 2, 3, 4, 5] as $day) {
            $val = $v['hours'][$day] ?? null;
            if ($val !== null && $val !== '') {
                $map[$day] = (float) $val;
            }
        }

        $school = School::first();
        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::EXPECTED_INSTRUCTIONAL_HOURS],
            ['value' => $map ? json_encode($map) : ''],
        );

        return $this->backToTab('academic')->with('success', 'Expected instructional hours updated.');
    }

    public function storeGradeBand(Request $request)
    {
        $v = $request->validate([
            'label'      => 'required|string|max:20',
            'min_score'  => 'required|numeric|min:0|max:100',
            'max_score'  => 'required|numeric|min:0|max:100|gte:min_score',
            'remark'     => 'nullable|string|max:60',
            'grade_min'  => 'nullable|integer|min:1|max:20|required_with:grade_max',
            'grade_max'  => 'nullable|integer|min:1|max:20|gte:grade_min|required_with:grade_min',
        ]);
        $school = School::first();
        if ($conflict = $this->gradeBandConflict($school, $v)) {
            return $this->backToTab('grading')->with('error', $conflict);
        }
        GradingScale::create([
            'school_id' => $school->id,
            'purpose'   => null,
            'label'     => $v['label'],
            'min_score' => $v['min_score'],
            'max_score' => $v['max_score'],
            'remark'    => $v['remark'] ?? null,
            'grade_min' => $v['grade_min'] ?? null,
            'grade_max' => $v['grade_max'] ?? null,
            'display_order' => (int) $school->gradingScales()->whereNull('purpose')->max('display_order') + 1,
        ]);
        return $this->backToTab('grading')->with('success', 'Grade band added.');
    }

    public function updateGradeBand(Request $request, GradingScale $band)
    {
        $v = $request->validate([
            'label'      => 'required|string|max:20',
            'min_score'  => 'required|numeric|min:0|max:100',
            'max_score'  => 'required|numeric|min:0|max:100|gte:min_score',
            'remark'     => 'nullable|string|max:60',
            'grade_min'  => 'nullable|integer|min:1|max:20|required_with:grade_max',
            'grade_max'  => 'nullable|integer|min:1|max:20|gte:grade_min|required_with:grade_min',
        ]);
        if ($conflict = $this->gradeBandConflict(School::first(), $v, $band->id)) {
            return $this->backToTab('grading')->with('error', $conflict);
        }
        $band->update($v + ['grade_min' => $v['grade_min'] ?? null, 'grade_max' => $v['grade_max'] ?? null]);
        return $this->backToTab('grading')->with('success', 'Grade band updated.');
    }

    /**
     * Guards the grade key against ambiguity before saving a band: two SPECIFIC
     * grade ranges that overlap (which grade a mark should use becomes ambiguous),
     * or two bands within the same grade range whose score ranges overlap. A null
     * grade range ("all grades") is the intended fallback and never conflicts.
     * Returns an error message, or null if the band is safe to save.
     */
    private function gradeBandConflict(School $school, array $v, ?int $ignoreId = null): ?string
    {
        $gMin = $v['grade_min'] ?? null;
        $gMax = $v['grade_max'] ?? null;

        $bands = $school->gradingScales()->whereNull('purpose')
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get();

        foreach ($bands as $b) {
            $sameGroup = ($b->grade_min == $gMin && $b->grade_max == $gMax);

            // Two different specific grade ranges that overlap → ambiguous scale.
            if (! $sameGroup && $gMin !== null && $b->grade_min !== null
                && $gMin <= $b->grade_max && $b->grade_min <= $gMax) {
                return "Grades {$gMin}-{$gMax} overlap another scale (grades {$b->grade_min}-{$b->grade_max}). Grade ranges must not overlap.";
            }

            // Same grade range, overlapping score bands → a mark could match two grades.
            if ($sameGroup && $v['min_score'] <= $b->max_score && $b->min_score <= $v['max_score']) {
                $range = ($gMin === null) ? 'all grades' : "grades {$gMin}-{$gMax}";
                return "Score range {$v['min_score']}-{$v['max_score']} overlaps the \"{$b->label}\" band ({$b->min_score}-{$b->max_score}) for {$range}.";
            }
        }

        return null;
    }

    public function destroyGradeBand(GradingScale $band)
    {
        $band->delete();
        return $this->backToTab('grading')->with('success', 'Grade band removed.');
    }

    public function toggleSubjectTotal(Request $request, Subject $subject)
    {
        $subject->update(['counts_toward_total' => $request->boolean('counts')]);
        $state = $subject->counts_toward_total ? 'is now Mark added' : 'is now Graded';
        return $this->backToTab('academic')->with('success', "\"{$subject->name}\" {$state}.");
    }

    public function destroySubject(Subject $subject)
    {
        $subject->delete();
        return $this->backToTab('academic')->with('success', "Subject deleted.");
    }

    // ---- Grades ----

    public function storePeriod(Request $request)
    {
        $validated = $request->validate([
            'label'      => 'required|string|max:60',
            'start_time' => 'nullable|date_format:H:i',
            'end_time'   => 'nullable|date_format:H:i',
            'is_break'   => 'nullable|boolean',
        ]);
        $school = School::first();
        Period::create([
            'school_id'     => $school?->id,
            'label'         => $validated['label'],
            'start_time'    => $validated['start_time'] ?? null,
            'end_time'      => $validated['end_time'] ?? null,
            'is_break'      => $request->boolean('is_break'),
            'display_order' => (int) (Period::ordered($school?->id)->max('display_order')) + 1,
        ]);
        return $this->backToTab('timetable')->with('success', "Period \"{$validated['label']}\" added.");
    }

    public function destroyPeriod(Period $period)
    {
        $period->delete(); // lessons in this period cascade away
        return $this->backToTab('timetable')->with('success', 'Period removed.');
    }

    public function storeGrade(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:100|unique:grades,name']);
        Grade::create($validated);
        return $this->backToTab('academic')->with('success', "Grade \"{$validated['name']}\" added.");
    }

    public function setGradeColor(Request $request, Grade $grade)
    {
        $request->validate(['color' => 'nullable|in:'.implode(',', array_keys(Grade::COLORS))]);
        $grade->color = $request->input('color') ?: null;
        $grade->save();

        return $this->backToTab('academic');
    }

    /**
     * Toggle whether pupils in this grade can use the student-login flow.
     * Off for grades too young to sign themselves in (e.g. Nursery).
     */
    public function toggleGradeLogin(Request $request, Grade $grade)
    {
        $grade->update(['student_login_enabled' => $request->boolean('enabled')]);

        return $this->backToTab('academic')->with('success', $grade->student_login_enabled
            ? "\"{$grade->name}\" pupils can now log in."
            : "\"{$grade->name}\" hidden from the student login.");
    }

    public function destroyGrade(Grade $grade)
    {
        if (Offering::where('grade_id', $grade->id)->exists()) {
            return $this->backToTab('academic')->with('error', "Cannot delete \"{$grade->name}\" — it has classes assigned.");
        }
        $grade->delete();
        return $this->backToTab('academic')->with('success', "Grade deleted.");
    }

    // ---- Staff statuses (staff list) ----

    public function storeStaffStatus(Request $request)
    {
        $v = $request->validate([
            'label'       => 'required|string|max:20',
            'description' => 'nullable|string|max:100',
        ]);
        $school = School::first();
        \App\Models\StaffStatus::create([
            'school_id'     => $school?->id,
            'label'         => $v['label'],
            'description'   => $v['description'] ?? null,
            'display_order' => (int) \App\Models\StaffStatus::max('display_order') + 1,
        ]);

        return $this->backToTab('academic')->with('success', "Staff status \"{$v['label']}\" added.");
    }

    public function destroyStaffStatus(\App\Models\StaffStatus $staffStatus)
    {
        // Any staff currently on this status keep their record; the status link nulls out.
        $staffStatus->delete();

        return $this->backToTab('academic')->with('success', 'Staff status removed.');
    }

    // ---- Teacher assignments ----

    public function assignTeacher(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'offering_id' => 'required|exists:offerings,id',
        ]);

        DB::table('teacher_offering')->insertOrIgnore([
            'user_id' => $validated['user_id'],
            'offering_id' => $validated['offering_id'],
            'principal' => false,
        ]);

        return $this->backToTab('classes')->with('success', 'Teacher assigned.');
    }

    public function removeTeacher(Request $request)
    {
        DB::table('teacher_offering')
            ->where('user_id', $request->input('user_id'))
            ->where('offering_id', $request->input('offering_id'))
            ->delete();

        return $this->backToTab('classes')->with('success', 'Teacher removed.');
    }

    /**
     * Bulk-update the subject-teacher map for a single offering. The
     * form on Settings → Classes submits one row per subject (subject id
     * keyed map of user ids; empty values clear the assignment). We
     * delete and re-insert in a transaction so the matrix is always
     * coherent for this offering.
     */
    public function setSubjectTeachers(Request $request)
    {
        // Submission shape: teachers[<subject_id>][] = <user_id> per
        // teacher (zero or more per subject). Each (offering, subject,
        // user) tuple becomes one teacher_assignments row — co-teaching
        // is allowed.
        $validated = $request->validate([
            'offering_id' => 'required|exists:offerings,id',
            'teachers' => 'array',
            'teachers.*' => 'array',
            'teachers.*.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($validated) {
            DB::table('teacher_assignments')
                ->where('offering_id', $validated['offering_id'])
                ->delete();

            $rows = [];
            foreach ($validated['teachers'] ?? [] as $subjectId => $userIds) {
                foreach ((array) $userIds as $userId) {
                    if (empty($userId)) {
                        continue;
                    }
                    $rows[] = [
                        'user_id' => (int) $userId,
                        'offering_id' => (int) $validated['offering_id'],
                        'subject_id' => (int) $subjectId,
                        'is_class_teacher' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (!empty($rows)) {
                DB::table('teacher_assignments')->insert($rows);
            }
        });

        return $this->backToTab('classes')->with('success', 'Subject teachers updated.');
    }

    /**
     * Set (or clear) the class principal for the offering. At most one
     * principal per offering. Empty user_id clears the principal — the
     * teacher line on reports goes blank in that case.
     */
    public function setPrincipal(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'offering_id' => 'required|exists:offerings,id',
        ]);

        DB::transaction(function () use ($validated) {
            DB::table('teacher_offering')
                ->where('offering_id', $validated['offering_id'])
                ->update(['principal' => false]);

            if (!empty($validated['user_id'])) {
                DB::table('teacher_offering')
                    ->where('offering_id', $validated['offering_id'])
                    ->where('user_id', $validated['user_id'])
                    ->update(['principal' => true]);
            }
        });

        return $this->backToTab('classes')->with('success', empty($validated['user_id'])
            ? 'Class principal cleared.'
            : 'Class principal updated.');
    }

    // ---- Class (offering) management ----

    /**
     * Add an extra class section for an existing grade in the current
     * school year. Schools with multiple sections per grade use this —
     * the rollover creates a single offering per grade by default, and
     * this is how you'd add a "Grade 1 B" alongside the auto-created
     * "Grade 1".
     */
    public function addOffering(Request $request)
    {
        $validated = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'name' => 'required|string|max:50',
        ]);

        $year = Schoolyear::current() ?? Schoolyear::latest();
        if (!$year) {
            return $this->backToTab('classes')->with('error', 'No school year configured.');
        }

        Offering::create([
            'schoolyear_id' => $year->id,
            'grade_id' => $validated['grade_id'],
            'name' => trim($validated['name']),
        ]);

        return $this->backToTab('classes')->with('success', 'Class section added.');
    }

    public function renameOffering(Request $request, Offering $offering)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:50',
        ]);

        $offering->update(['name' => $validated['name'] ? trim($validated['name']) : null]);

        return $this->backToTab('classes')->with('success', 'Class renamed.');
    }

    public function deleteOffering(Offering $offering)
    {
        if ($offering->enrollments()->exists() || $offering->teachers()->exists()) {
            return $this->backToTab('classes')->with('error', 'Remove students and teachers from this class before deleting it.');
        }
        $offering->delete();

        return $this->backToTab('classes')->with('success', 'Class deleted.');
    }

    // ---- Subjects per class ----
    // Subjects don't differ by term, so a class's subject list (and each subject's
    // "counts toward total" flag) is written across every term of the class's school
    // year. The subject_term_offering pivot keeps its term_id column; every term of a
    // class simply holds the same set.

    public function addClassSubject(Request $request)
    {
        $validated = $request->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'offering_id' => 'required|exists:offerings,id',
        ]);

        $offering = Offering::findOrFail($validated['offering_id']);
        foreach ($this->offeringTermIds($offering) as $termId) {
            DB::table('subject_term_offering')->updateOrInsert(
                ['subject_id' => $validated['subject_id'], 'term_id' => $termId, 'offering_id' => $validated['offering_id']],
                []
            );
        }

        return $this->backToTab('classes')->with('success', 'Subject added to the class.');
    }

    public function removeClassSubject(Request $request)
    {
        // Remove the subject from the class across every term.
        DB::table('subject_term_offering')
            ->where('subject_id', $request->input('subject_id'))
            ->where('offering_id', $request->input('offering_id'))
            ->delete();

        return $this->backToTab('classes')->with('success', 'Subject removed from the class.');
    }

    /**
     * Per class, whether a subject counts toward the term total. Written across every
     * term of the class; the subject's school-wide default (Settings → Subjects) still
     * governs classes left untouched (pivot value null = inherit).
     */
    public function setClassSubjectCounting(Request $request)
    {
        $validated = $request->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'offering_id' => 'required|exists:offerings,id',
        ]);

        DB::table('subject_term_offering')
            ->where('subject_id', $validated['subject_id'])
            ->where('offering_id', $validated['offering_id'])
            ->update(['counts_toward_total' => $request->boolean('counts')]);

        return $this->backToTab('classes')->with('success', 'Updated whether the subject counts toward the total.');
    }

    /** Term ids of an offering's school year, for applying subject changes to every term. */
    private function offeringTermIds(Offering $offering)
    {
        return $offering->schoolyear ? $offering->schoolyear->terms()->pluck('id') : collect();
    }
}
