<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Support\Installer\CountryPresets;
use App\Support\Installer\InstallerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * First-run setup wizard. Each step validates and stashes its data in the
 * session; the final step commits everything via InstallerService and logs the
 * new headmaster in. Access is gated by the EnsureInstalled middleware.
 */
class InstallController extends Controller
{
    public function index()
    {
        return view('install.welcome');
    }

    // ---- Step 1: school ---------------------------------------------------

    public function school()
    {
        return view('install.school', [
            'countries' => CountryPresets::countries(),
            'data' => session('install.school', []),
        ]);
    }

    public function storeSchool(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|in:'.implode(',', array_keys(CountryPresets::countries())),
            'address' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:4096',
        ]);

        $school = session('install.school', []);
        $school = array_merge($school, [
            'name' => $validated['name'],
            'country' => $validated['country'],
            'address' => $validated['address'] ?? null,
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $name = 'logos/install-'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('logos'), $name);
            $school['logo_path'] = $name;
        }

        session(['install.school' => $school]);

        return redirect()->route('install.structure');
    }

    // ---- Step 2: school year + terms -------------------------------------

    public function structure()
    {
        if (! session('install.school')) {
            return redirect()->route('install.school');
        }

        $data = session('install.structure', $this->defaultYear());

        return view('install.structure', [
            'data' => $data,
            'terms' => session('install.terms') ?? InstallerService::defaultTerms($data['start'], $data['end']),
        ]);
    }

    public function storeStructure(Request $request)
    {
        $validated = $request->validate([
            'year_name' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'terms' => 'nullable|array',
            'terms.*.name' => 'required_with:terms|string|max:255',
            'terms.*.start' => 'required_with:terms|date',
            'terms.*.end' => 'required_with:terms|date',
        ]);

        session([
            'install.structure' => [
                'name' => $validated['year_name'],
                'start' => $validated['start'],
                'end' => $validated['end'],
            ],
            'install.terms' => $validated['terms'] ?? null,
        ]);

        return redirect()->route('install.classes');
    }

    // ---- Step 3: classes + subjects --------------------------------------

    public function classes()
    {
        if (! session('install.structure')) {
            return redirect()->route('install.structure');
        }

        $country = session('install.school.country');

        return view('install.classes', [
            'preset' => CountryPresets::for($country),
            'countryName' => CountryPresets::countries()[$country] ?? $country,
            'gradeClasses' => session('install.grade_classes'), // ['Grade 1' => 2] or null
            'manualGrades' => session('install.grades', ''),
            'selectedSubjects' => session('install.subjects'),  // null until they've picked
        ]);
    }

    public function storeClasses(Request $request)
    {
        $validated = $request->validate([
            'grades' => 'nullable|array',
            'grades.*' => 'string|max:255',
            'classes' => 'nullable|array',
            'manual_grades' => 'nullable|string',
            'subjects' => 'nullable|array',
            'subjects.*' => 'string|max:255',
            'custom_subjects' => 'nullable|string',
        ]);

        $preset = CountryPresets::for(session('install.school.country'));

        if ($preset) {
            // Ticked grades, each with a class count (defaults to 1).
            $counts = $request->input('classes', []);
            $gradeClasses = [];
            foreach ($validated['grades'] ?? [] as $name) {
                $gradeClasses[$name] = max(1, (int) ($counts[$name] ?? 1));
            }
            session(['install.grade_classes' => $gradeClasses]);
            session()->forget('install.grades');
        } else {
            // No preset: grade names typed one per line, one class each.
            session(['install.grades' => $validated['manual_grades'] ?? '']);
            session()->forget('install.grade_classes');
        }

        // Subject checklist (+ a custom box); left unset means "use the preset default".
        if ($request->boolean('subjects_present')) {
            $picked = array_map('trim', $validated['subjects'] ?? []);
            $custom = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['custom_subjects'] ?? '')));
            $subjects = array_values(array_unique(array_filter(array_merge($picked, $custom))));
            session(['install.subjects' => $subjects]);
        } else {
            session()->forget('install.subjects');
        }

        return redirect()->route('install.features');
    }

    // ---- Step 4: features (optional modules) -----------------------------

    public function features()
    {
        if (! session('install.structure')) {
            return redirect()->route('install.structure');
        }

        $modules = collect(config('modules'))
            ->reject(fn ($m) => $m['always_on'] ?? false)
            ->map(fn ($m, $slug) => [
                'slug' => $slug,
                'label' => $m['label'],
                'description' => $m['description'] ?? '',
                'default' => $m['default_enabled'] ?? false,
                'beta' => $m['beta'] ?? false,
            ])->values();

        return view('install.features', [
            'modules' => $modules,
            'selected' => session('install.modules'), // null until they've chosen
        ]);
    }

    public function storeFeatures(Request $request)
    {
        $validated = $request->validate([
            'modules' => 'nullable|array',
            'modules.*' => 'string',
        ]);

        session(['install.modules' => $validated['modules'] ?? []]);

        return redirect()->route('install.admin');
    }

    // ---- Step 5: administrator account -----------------------------------

    public function admin()
    {
        if (! session('install.structure')) {
            return redirect()->route('install.structure');
        }

        return view('install.admin', ['data' => session('install.admin', [])]);
    }

    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        session(['install.admin' => $validated]);

        return redirect()->route('install.review');
    }

    // ---- Step 4: review + commit -----------------------------------------

    public function review()
    {
        if (! session('install.admin')) {
            return redirect()->route('install.admin');
        }

        $country = session('install.school.country');

        return view('install.review', [
            'school' => session('install.school'),
            'structure' => session('install.structure'),
            'admin' => session('install.admin'),
            'preset' => CountryPresets::for($country),
            'countryName' => CountryPresets::countries()[$country] ?? $country,
            'manualGrades' => $this->manualGradeList(),
            'gradeClasses' => session('install.grade_classes'), // ['Grade 1' => 2] or null
            'selectedSubjects' => session('install.subjects'),
            'terms' => session('install.terms') ?? InstallerService::defaultTerms(
                session('install.structure.start'),
                session('install.structure.end'),
            ),
        ]);
    }

    public function complete(Request $request)
    {
        if (! session('install.admin') || ! session('install.structure') || ! session('install.school')) {
            return redirect()->route('install.school');
        }

        $admin = InstallerService::complete([
            'school' => session('install.school'),
            'year' => session('install.structure'),
            'admin' => session('install.admin'),
            'grades' => $this->manualGradeList(),
            'gradeClasses' => session('install.grade_classes'), // ['Grade 1' => 2] or null (non-preset)
            'subjects' => session('install.subjects'), // null => installer uses the preset default
            'terms' => session('install.terms'),       // null => installer uses the even split
            'modules' => session('install.modules'),   // null => installer enables the defaults
        ]);

        $request->session()->forget('install');
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect('/')->with('success', "You're all set, welcome to KUBO! Everything's ready, you can start adding teachers and students whenever you like.");
    }

    /** Grade names typed in the structure step (for countries without a preset). */
    private function manualGradeList(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) session('install.grades', '')))
            ->map(fn ($g) => trim($g))
            ->filter()
            ->values()
            ->all();
    }

    /** A sensible default school year spanning roughly the next academic year. */
    private function defaultYear(): array
    {
        // Default to the year the school is about to start. From June onwards
        // (the run-up to the September start) that's the coming year; earlier in
        // the calendar year it's the one already in session.
        $now = now();
        $startYear = $now->month >= 6 ? $now->year : $now->year - 1;

        return [
            'name' => $startYear.'-'.($startYear + 1),
            'start' => $startYear.'-09-01',
            'end' => ($startYear + 1).'-08-31',
        ];
    }
}
