<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\Offering;
use App\Models\Profile;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Albreda Lower Basic School — demo data for the monthly-marks grid + result
 * sheet / analysis / histogram feature.
 *
 *   php artisan db:seed --class=AlbredaSeeder   (run on a fresh database)
 *
 * Two classes, on purpose different subject sets so the reports prove they
 * follow each grade's own subjects:
 *  - Grade 6A: English, Mathematics, Science, S.E.S. — exactly the paper sheet
 *    (End of Term 2 exam, 2025/2026; scores transcribed from a photo, verify).
 *  - Grade 3A: English, Mathematics, Integrated Studies — as grades 1-4 run it
 *    (no separate Science / S.E.S.).
 *
 * Albreda uses the WAEC A1–F9 grade key (not KUBO's default 1/4/5/6/8/9).
 */
class AlbredaSeeder extends Seeder
{
    private AssessmentType $testType;
    private AssessmentType $examType;

    /** School name — overridden by subclasses (e.g. Bakoteh) that reuse this data. */
    protected function schoolName(): string
    {
        return 'Albreda Lower Basic School';
    }

    /** Staff login password for this demo school. */
    protected function password(): string
    {
        return 'albreda';
    }

    /** One-page term-card layout: 'albreda' (WAEC card) or 'default'. */
    protected function termCardLayout(): string
    {
        return 'albreda';
    }

    /** Report style: 'term' (one-page cards) or 'book' (report/assessment book). */
    protected function reportType(): string
    {
        return 'term';
    }

    public function run(): void
    {
        if (! Role::where('name', 'headmaster')->where('guard_name', 'web')->exists()) {
            $this->call(RolesAndPermissionsSeeder::class);
        }

        $school = School::firstOrCreate(
            ['name' => $this->schoolName()],
            ['timezone' => 'Africa/Banjul'],
        );

        $this->testType = AssessmentType::firstOrCreate(['school_id' => $school->id, 'name' => 'Test'], ['weight' => 0.25, 'display_order' => 1]);
        $this->examType = AssessmentType::firstOrCreate(['school_id' => $school->id, 'name' => 'Exam'], ['weight' => 0.75, 'display_order' => 2]);

        // Albreda is a government school: the Health module is not used, so ship it
        // off. Subclasses that do demo Health (e.g. the Bakoteh workshop) opt in.
        $modules = collect(config('modules'))
            ->filter(fn ($d) => $d['default_enabled'] ?? true)
            ->keys()->reject(fn ($k) => $k === 'health' && ! $this->healthModuleEnabled())->values()->all();
        $school->configs()->updateOrCreate(['key' => \App\Models\SchoolConfig::ENABLED_MODULES], ['value' => $modules]);

        // Per-school report format: which one-page card layout, and term-card vs report-book.
        $school->configs()->updateOrCreate(['key' => \App\Models\SchoolConfig::TERM_CARD_LAYOUT], ['value' => $this->termCardLayout()]);
        $school->configs()->updateOrCreate(['key' => \App\Models\SchoolConfig::REPORT_TYPE], ['value' => $this->reportType()]);

        foreach ($this->gradeKeyBands() as $i => [$label, $min, $max, $remark]) {
            GradingScale::firstOrCreate(
                ['school_id' => $school->id, 'purpose' => null, 'label' => $label],
                ['min_score' => $min, 'max_score' => $max, 'remark' => $remark, 'display_order' => $i + 1],
            );
        }

        $year = Schoolyear::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2025-2026'],
            ['start' => '2025-09-01', 'end' => '2026-08-31'],
        );

        // Public-school calendar (confirmed by Albreda teachers): Term 1 Oct-Dec,
        // Term 2 Jan-Apr, Term 3 May-Jul. The last month of each term is the exam.
        $termDefs = [['Term 1', '2025-10-01', '2025-12-20'], ['Term 2', '2026-01-05', '2026-04-30'], ['Term 3', '2026-05-01', '2026-07-25']];
        $terms = [];
        foreach ($termDefs as [$tn, $ts, $te]) {
            $terms[] = Term::firstOrCreate(['schoolyear_id' => $year->id, 'name' => $tn], ['start' => $ts, 'end' => $te]);
        }

        $offerings = $this->seedClasses($school, $year, $terms);
        $this->seedStaff($school, $offerings);
        $this->afterSeed($school, $offerings);

        $this->command?->info($this->schoolName().' seeded.');
    }

    /** Whether to keep the Health module on for this school (default: government schools ship it off). */
    protected function healthModuleEnabled(): bool
    {
        return false;
    }

    /** Hook for subclasses to seed extra data once the school, classes and staff exist. */
    protected function afterSeed(School $school, array $offerings): void
    {
        // no-op by default
    }

    /** Grade-key bands: [label, min, max, interpretation]. Albreda uses WAEC A1-F9. */
    protected function gradeKeyBands(): array
    {
        return [
            ['A1', 80, 100, 'Excellent'], ['B2', 70, 79, 'Very Good'], ['B3', 65, 69, 'Good'],
            ['C4', 60, 64, 'Credit'], ['C5', 55, 59, 'Credit'], ['C6', 50, 54, 'Credit'],
            ['D7', 45, 49, 'Pass'], ['E8', 40, 44, 'Pass'], ['F9', 0, 39, 'Fail'],
        ];
    }

    /** Head last name, and one principal-teacher last name per class. */
    protected function headLastName(): string
    {
        return 'Badjie';
    }

    protected function teacherLastNames(): array
    {
        return ['Sanneh', 'Jarju'];
    }

    /** @return Offering[] the classes to seed, with their subject sets and rosters. */
    protected function seedClasses(School $school, Schoolyear $year, array $terms): array
    {
        // Grade 6A roster: [first, last, gender, English, Maths, Science, S.E.S.]. null = absent.
        $grade6 = [
            ['Kumba', 'Jaw', 'F', 92, 78, 82, 85], ['Malang', 'Saidy', 'M', 85, 88, 77, 85],
            ['Fatou', 'Taber', 'F', 78, 78, 80, 71], ['Isatou', 'Janneh', 'F', 85, 68, 67, 72],
            ['Fatou', 'Ndong', 'F', 90, 48, 69, 84], ['Fatou', 'Traore', 'F', 85, 43, 72, 71],
            ['Alieu', 'Sowe', 'M', 60, 65, 81, 55], ['Salla', 'Njie', 'F', 88, 60, 65, 55],
            ['Mariama', 'Dampha', 'F', 65, 53, 40, 75], ['Aminata', 'Sarko', 'F', 70, 60, 45, 55],
            ['Ebrima', 'A Chan', 'M', 73, 53, 73, 58], ['Abdoulie', 'Sanyang', 'M', 60, 44, 46, 50],
            ['Sally', 'Baye', 'F', 50, 40, 50, 45], ['Isatou', 'Mbaye', 'F', 70, 65, 54, 45],
            ['Pierre', 'Mendy', 'M', 50, 42, 46, 55], ['Adama', 'Bah', 'M', 50, 73, 25, 50],
            ['Fatoumatta', 'Chan', 'F', 45, 44, 55, 35], ['Ebrima', 'Sowe', 'M', 60, 50, 35, 35],
            ['Fama', 'Nyass', 'F', 50, 35, 50, 50], ['Fatou', 'Jallow', 'F', 20, 63, 49, 30],
            ['Amadou', 'Jallow', 'M', 30, 33, 54, 35], ['Abdou', 'Ndoye', 'M', 45, 23, 48, 35],
            ['Papa', 'Dumbuya', 'M', 35, 13, 65, 35], ['Naffie', 'Jobe', 'F', 43, 10, 43, 50],
            ['Abdoulie', 'Bah', 'M', 10, 35, 50, 45], ['Omar', 'Gai', 'M', 35, 10, 40, 35],
            ['Mustapha', 'Njie', 'M', 20, 68, 15, 30], ['Cherno', 'Njie', 'M', 40, 15, 68, 10],
            ['Kebba', 'Saidy', 'M', 45, 20, 50, 25], ['Mamadi', 'Janko', 'M', 40, 35, null, 23], // absent for Science
        ];

        // Grade 3A roster: [first, last, gender, English, Maths, Integrated Studies].
        $grade3 = [
            ['Awa', 'Ceesay', 'F', 78, 70, 82], ['Lamin', 'Touray', 'M', 55, 60, 58],
            ['Fatou', 'Colley', 'F', 84, 66, 79], ['Ousman', 'Jarju', 'M', 45, 38, 52],
            ['Mariama', 'Bojang', 'F', 62, 71, 68], ['Sang', 'Manneh', 'M', 38, 42, 40],
            ['Binta', 'Camara', 'F', 90, 85, 88], ['Modou', 'Ceesay', 'M', 50, 48, 55],
            ['Isatou', 'Darboe', 'F', 66, 59, 62], ['Ebrima', 'Fofana', 'M', 33, 40, 35],
            ['Haddy', 'Sowe', 'F', 72, 68, 74], ['Alagie', 'Drammeh', 'M', 58, 52, 60],
            ['Rohey', 'Barrow', 'F', 47, 44, 49], ['Momodou', 'Kinteh', 'M', 25, 30, 28],
            ['Jainaba', 'Sanyang', 'F', 81, 77, 83], ['Yankuba', 'Badjie', 'M', 60, 55, 58],
            ['Fatoumatta', 'Njie', 'F', 53, 49, 51], ['Sulayman', 'Conteh', 'M', 40, 35, 38],
            ['Adama', 'Jammeh', 'F', 68, 72, 70], ['Bakary', 'Sillah', 'M', 44, 40, 46],
        ];

        // Grade 6 first, so it stays offering #1 (the class the demo links use).
        return [
            $this->seedClass($school, $year, $terms, 'Grade 6', 'A', ['English language', 'Mathematics', 'Science', 'S.E.S.'], $grade6),
            $this->seedClass($school, $year, $terms, 'Grade 3', 'A', ['English language', 'Mathematics', 'Integrated Studies'], $grade3),
        ];
    }

    protected function seedStaff(School $school, array $offerings): void
    {
        $head = User::create(['first_name' => 'Mr', 'last_name' => $this->headLastName(), 'password' => bcrypt($this->password()), 'school_id' => $school->id, 'archived' => false]);
        $head->assignRole('headmaster');

        $names = $this->teacherLastNames();
        foreach ($offerings as $i => $offering) {
            $teacher = User::create(['first_name' => 'Mr', 'last_name' => $names[$i] ?? ('Teacher '.($i + 1)), 'password' => bcrypt($this->password()), 'school_id' => $school->id, 'archived' => false]);
            $teacher->assignRole('teacher');
            DB::table('teacher_offering')->insert(['user_id' => $teacher->id, 'offering_id' => $offering->id, 'principal' => 1]);
        }
    }

    /**
     * Seed one class with its own subject set: attaches the subjects each term,
     * creates a Term-2 exam and a Term-3 May test per subject, and enrolls the
     * roster with marks. Roster rows are [first, last, gender, ...scores] with
     * the scores in $subjectNames order (null = absent).
     */
    protected function seedClass(School $school, Schoolyear $year, array $terms, string $gradeName, string $section, array $subjectNames, array $roster, array $graded = []): Offering
    {
        [$term2, $term3] = [$terms[1], $terms[2]];

        $subjects = [];
        foreach ($subjectNames as $name) {
            $subjects[$name] = Subject::firstOrCreate(['name' => $name, 'school_id' => $school->id], ['counts_toward_total' => ! in_array($name, $graded, true)]);
        }

        $grade = Grade::firstOrCreate(['name' => $gradeName, 'school_id' => $school->id]);
        $offering = Offering::firstOrCreate(
            ['schoolyear_id' => $year->id, 'grade_id' => $grade->id, 'name' => $section],
            ['activated' => 1],
        );
        foreach ($subjectNames as $name) {
            foreach ($terms as $t) {
                DB::table('subject_term_offering')->updateOrInsert([
                    'offering_id' => $offering->id, 'term_id' => $t->id, 'subject_id' => $subjects[$name]->id,
                ]);
            }
        }

        // One Term-2 exam + one Term-3 May test per subject.
        $exams = [];
        $t3Exams = [];
        foreach ($subjectNames as $name) {
            $exams[$name] = Assessment::create([
                'assessment_type_id' => $this->examType->id, 'subject_id' => $subjects[$name]->id,
                'offering_id' => $offering->id, 'term_id' => $term2->id,
                'name' => 'End of Term 2 Exam', 'date' => '2026-04-05', 'max_score' => 100, 'confirmed' => 1,
            ]);
            $t3Exams[$name] = Assessment::create([
                'assessment_type_id' => $this->testType->id, 'subject_id' => $subjects[$name]->id,
                'offering_id' => $offering->id, 'term_id' => $term3->id,
                'name' => 'May Test', 'date' => '2026-05-15', 'max_score' => 100, 'confirmed' => 1,
            ]);
        }

        foreach ($roster as $idx => $row) {
            [$first, $last, $gender] = $row;
            $scores = array_slice($row, 3);

            $user = User::create([
                'first_name' => $first, 'last_name' => $last, 'password' => bcrypt('2013-06-15'),
                'school_id' => $school->id, 'archived' => false,
            ]);
            $user->assignRole('student');
            Profile::create(['user_id' => $user->id, 'gender' => $gender, 'birth_date' => Carbon::parse('2013-06-15')]);
            Enrollment::create(['user_id' => $user->id, 'offering_id' => $offering->id]);

            $now = Carbon::now()->toDateTimeString();
            foreach (array_values($subjectNames) as $subjIndex => $name) {
                $score = $scores[$subjIndex] ?? null;
                DB::table('assessment_scores')->insert([
                    'user_id' => $user->id, 'assessment_id' => $exams[$name]->id,
                    'score' => $score ?? 0, 'absent' => $score === null ? 1 : 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                // Term-3 May test: nudge each mark deterministically so it reads as a distinct set.
                $t3 = $score === null ? null : min(100, max(0, $score + ((($idx + 1) * 3 + ($subjIndex + 1) * 4) % 13) - 6));
                DB::table('assessment_scores')->insert([
                    'user_id' => $user->id, 'assessment_id' => $t3Exams[$name]->id,
                    'score' => $t3 ?? 0, 'absent' => $t3 === null ? 1 : 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        return $offering;
    }
}
