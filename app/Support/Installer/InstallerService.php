<?php

namespace App\Support\Installer;

use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\NatConfig;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * First-run setup. An instance is "installed" once a School row exists; the
 * installer wizard collects everything and commits it here in one transaction.
 */
class InstallerService
{
    public static function isInstalled(): bool
    {
        // Guard the pre-migration case so the app never white-screens.
        if (! Schema::hasTable('schools')) {
            return false;
        }

        if (School::query()->exists()) {
            return true;
        }

        // Fallback: an admin-level account already exists (legacy env-seeded
        // installs, and the test baseline) — treat that as set up so we don't
        // force the wizard on an instance that's already in use.
        return Schema::hasTable('model_has_roles')
            && DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', ['headmaster', 'admin', 'system_admin'])
                ->exists();
    }

    /**
     * Commit the wizard.
     *
     * @param  array  $data  [
     *     'school' => ['name', 'country', 'motto'?, 'address'?, 'logo_path'?],
     *     'year'   => ['name', 'start', 'end'],
     *     'admin'  => ['first_name', 'last_name', 'email'?, 'password'],
     *     'grades' => string[]  // explicit grade names (used when the country has no preset)
     *  ]
     * @return User the created administrator
     */
    public static function complete(array $data): User
    {
        static::ensureRoles();

        return DB::transaction(function () use ($data) {
            $country = $data['school']['country'] ?? 'OTHER';
            $preset = CountryPresets::for($country) ?? [];

            $school = School::create([
                'name' => $data['school']['name'],
                'motto' => $data['school']['motto'] ?? null,
                'address' => $data['school']['address'] ?? null,
                'logo_path' => $data['school']['logo_path'] ?? null,
                'timezone' => $preset['timezone'] ?? 'Africa/Banjul',
            ]);

            static::seedAssessmentScaffold($school, $preset);
            static::enableModules($school, $data['modules'] ?? null);

            $year = Schoolyear::create([
                'name' => $data['year']['name'],
                'start' => $data['year']['start'],
                'end' => $data['year']['end'],
                'school_id' => $school->id,
            ]);
            $terms = static::makeTerms($year, $data['terms'] ?? null);

            // Grade selection + per-grade class count from the wizard (['Grade 1' => 2]),
            // else the preset grades (or the names a non-preset school typed), one class each.
            $gradeClasses = $data['gradeClasses'] ?? null;
            $gradeNames = $gradeClasses
                ? array_keys($gradeClasses)
                : ($preset['grades'] ?? array_values(array_filter($data['grades'] ?? [])));
            $grades = collect($gradeNames)->map(fn ($name) => Grade::create(['name' => $name, 'school_id' => $school->id]));

            foreach ($grades as $grade) {
                $count = max(1, (int) ($gradeClasses[$grade->name] ?? 1));
                // One class keeps the year as the offering name (a plain "Grade 1");
                // multiple classes become sections A, B, C… under the grade.
                $sections = $count === 1 ? [$year->name] : array_map(fn ($i) => chr(65 + $i), range(0, $count - 1));
                foreach ($sections as $section) {
                    Offering::create([
                        'schoolyear_id' => $year->id,
                        'grade_id' => $grade->id,
                        'activated' => 1,
                        'name' => $section,
                    ]);
                }
            }

            // Subjects come from the wizard's checklist when given, else the preset
            // default; a non-preset school with no picks adds them later in Settings.
            $subjectNames = $data['subjects'] ?? $preset['subjects'] ?? [];
            $subjects = collect($subjectNames)
                ->mapWithKeys(fn ($name) => [$name => Subject::create(['name' => $name, 'school_id' => $school->id])]);

            if ($subjects->isNotEmpty()) {
                static::attachCurriculum($year, $terms, $subjects);
            }

            if (! empty($preset['nat'])) {
                static::seedNatConfig($school, $year, $grades, $subjects, $preset['nat']);
            }

            $admin = User::create([
                'first_name' => $data['admin']['first_name'],
                'last_name' => $data['admin']['last_name'],
                'email' => ($data['admin']['email'] ?? null) ?: null,
                'password' => bcrypt($data['admin']['password']),
                'archived' => false,
                'school_id' => $school->id,
            ]);
            // The first account is an admin: it runs the install and then configures
            // everyone else (headmaster, teachers, students) from inside KUBO.
            $admin->assignRole('admin');

            return $admin;
        });
    }

    /** Roles must exist before we can assign 'admin'. */
    private static function ensureRoles(): void
    {
        if (! Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {
            Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }
    }

    /** Test/Exam weights + the grade key. Uses the country preset's scale (e.g. the
     *  Gambian 1/4/5/6/8/9), falling back to A-F for countries without one. */
    private static function seedAssessmentScaffold(School $school, array $preset = []): void
    {
        AssessmentType::create(['school_id' => $school->id, 'name' => 'Test', 'weight' => 0.2500, 'display_order' => 1]);
        AssessmentType::create(['school_id' => $school->id, 'name' => 'Exam', 'weight' => 0.7500, 'display_order' => 2]);

        $scale = $preset['grade_scale'] ?? [
            ['label' => 'A', 'min_score' => 70, 'max_score' => 100, 'remark' => 'Excellent', 'display_order' => 1],
            ['label' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'remark' => 'Very Good', 'display_order' => 2],
            ['label' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'remark' => 'Good', 'display_order' => 3],
            ['label' => 'D', 'min_score' => 40, 'max_score' => 49.99, 'remark' => 'Fair', 'display_order' => 4],
            ['label' => 'E', 'min_score' => 30, 'max_score' => 39.99, 'remark' => 'Poor', 'display_order' => 5],
            ['label' => 'F', 'min_score' => 0, 'max_score' => 29.99, 'remark' => 'Fail', 'display_order' => 6],
        ];
        foreach ($scale as $g) {
            GradingScale::create(array_merge(['school_id' => $school->id], $g));
        }
    }

    /**
     * Enable the optional modules the school chose in the wizard, plus the
     * always-on core. When no selection is given (programmatic installs), fall
     * back to every default_enabled module.
     *
     * @param  string[]|null  $selected  optional-module slugs the school ticked
     */
    private static function enableModules(School $school, ?array $selected = null): void
    {
        $defs = collect(config('modules'));
        $alwaysOn = $defs->filter(fn ($m) => $m['always_on'] ?? false)->keys();

        if ($selected === null) {
            $enabled = $defs->filter(fn ($m) => ($m['default_enabled'] ?? false) || ($m['always_on'] ?? false))->keys();
        } else {
            $toggleable = $defs->reject(fn ($m) => $m['always_on'] ?? false)->keys();
            $enabled = $alwaysOn->merge(collect($selected)->intersect($toggleable))->unique();
        }

        $school->configs()->create(['key' => \App\Models\SchoolConfig::ENABLED_MODULES, 'value' => $enabled->values()->all()]);
    }

    /** The three terms the wizard shows: use what the school entered, else the even split. */
    private static function makeTerms(Schoolyear $year, ?array $given = null): array
    {
        $rows = $given ?: static::defaultTerms($year->start, $year->end);

        return collect($rows)->map(fn ($t) => Term::create([
            'schoolyear_id' => $year->id,
            'name' => $t['name'],
            'start' => $t['start'],
            'end' => $t['end'],
        ]))->all();
    }

    /** Three terms splitting the year into roughly equal thirds (the editable default). */
    public static function defaultTerms($start, $end): array
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        $days = max(1, $start->diffInDays($end));

        $t1End = $start->copy()->addDays(intdiv($days, 3));
        $t2End = $start->copy()->addDays(intdiv($days * 2, 3));

        return [
            ['name' => 'Term 1', 'start' => $start->toDateString(), 'end' => $t1End->toDateString()],
            ['name' => 'Term 2', 'start' => $t1End->toDateString(), 'end' => $t2End->toDateString()],
            ['name' => 'Term 3', 'start' => $t2End->toDateString(), 'end' => $end->toDateString()],
        ];
    }

    /** Attach every subject to every class for every term (the curriculum the scorebook reads). */
    private static function attachCurriculum(Schoolyear $year, array $terms, $subjects): void
    {
        $offeringIds = Offering::where('schoolyear_id', $year->id)->pluck('id');
        $subjectIds = collect($subjects)->map->id;

        $rows = [];
        foreach ($offeringIds as $offeringId) {
            foreach ($terms as $term) {
                foreach ($subjectIds as $subjectId) {
                    $rows[] = ['offering_id' => $offeringId, 'term_id' => $term->id, 'subject_id' => $subjectId];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('subject_term_offering')->insert($chunk);
        }
    }

    private static function seedNatConfig(School $school, Schoolyear $year, $grades, $subjects, array $nat): void
    {
        $config = NatConfig::create([
            'school_id' => $school->id,
            'schoolyear_id' => $year->id,
            'enabled' => true,
            'label' => 'National Assessment Test',
        ]);

        foreach ($nat as $gradeName => $subjectNames) {
            $grade = $grades->firstWhere('name', $gradeName);
            if (! $grade) {
                continue;
            }
            foreach ($subjectNames as $i => $subjectName) {
                $subject = $subjects->get($subjectName);
                if (! $subject) {
                    continue;
                }
                $config->subjects()->create([
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'max_score' => 100,
                    'display_order' => $i + 1,
                ]);
            }
        }
    }
}
