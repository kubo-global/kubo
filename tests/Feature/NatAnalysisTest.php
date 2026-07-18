<?php

namespace Tests\Feature;

use App\Domain\Nat\NatAnalysis;
use App\Http\Controllers\NewInterfaceControllers\NatReportController;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\Offering;
use App\Models\Profile;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NatAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build one NAT sitting (offering) with the given subjects and pupils.
     *
     * @param  array  $subjects  list of subject names
     * @param  array  $pupils    [firstName, lastName, gender('m'|'f'), [scores aligned to $subjects, null = absent]]
     * @return array{offering: Offering, type: AssessmentType}
     */
    private function seedSitting(string $yearName, string $gradeName, array $subjects, array $pupils): array
    {
        $school = School::factory()->create();
        $type = AssessmentType::factory()->create([
            'school_id' => $school->id,
            'name' => 'National Assessment Test',
        ]);
        $year = Schoolyear::factory()->create(['name' => $yearName]);
        $grade = Grade::factory()->create(['name' => $gradeName]);
        $offering = Offering::factory()->create([
            'schoolyear_id' => $year->id,
            'grade_id' => $grade->id,
        ]);
        $term = Term::factory()->create(['schoolyear_id' => $year->id]);

        $assessments = collect($subjects)->map(function ($name) use ($type, $offering, $term) {
            $subject = Subject::factory()->create(['name' => $name]);

            return Assessment::factory()->create([
                'assessment_type_id' => $type->id,
                'subject_id' => $subject->id,
                'offering_id' => $offering->id,
                'term_id' => $term->id,
                'max_score' => 100,
                'name' => "NAT {$name}",
            ]);
        })->values();

        foreach ($pupils as [$first, $last, $gender, $scores]) {
            $student = Student::factory()->create(['first_name' => $first, 'last_name' => $last]);
            Profile::factory()->create(['user_id' => $student->id, 'gender' => $gender]);
            Enrollment::factory()->create(['user_id' => $student->id, 'offering_id' => $offering->id]);

            foreach ($assessments as $i => $assessment) {
                $score = $scores[$i];
                AssessmentScore::create([
                    'user_id' => $student->id,
                    'assessment_id' => $assessment->id,
                    'score' => $score,            // null => absent
                    'absent' => $score === null,
                ]);
            }
        }

        return ['offering' => $offering->fresh(), 'type' => $type];
    }

    /** Pull the All/Male/Female stats for a given subject out of the breakdowns. */
    private function group($breakdowns, string $subject, string $label): array
    {
        $row = $breakdowns->first(fn ($b) => $b['subject']->name === $subject);

        return $row['groups'][$label];
    }

    #[Test]
    public function it_computes_the_2024_grade_5_analysis(): void
    {
        // The Swallow Lower Basic School, NAT 2024 Grade 5 (verified against WAEC printout).
        $subjects = ['English', 'Mathematics', 'Science', 'Social & Environmental Studies'];
        $pupils = [
            ['Foday', 'Badjie', 'm', [85, 82, 76, 74]],
            ['Mansata', 'Badjie', 'f', [71, 62, 73, 72]],
            ['Abdoulie', 'Ceesay', 'm', [75, 66, 71, 76]],
            ['Fatoumatta', 'Drammeh', 'f', [89, 82, 76, 72]],
            ['Fatoumatta', 'Dukureh', 'f', [78, 68, 58, 62]],
            ['Kaddijatou', 'Dukureh', 'f', [85, 76, 69, 74]],
            ['Kodou', 'Gaye', 'f', [75, 70, 67, 74]],
            ['Awa', 'Gumaneh', 'f', [76, 62, 76, 68]],
            ['Mariama', 'Jah', 'f', [64, 66, 56, 60]],
            ['Fatou', 'Jarju', 'f', [76, 70, 67, 74]],
            ['Modou', 'Joof', 'm', [78, 64, 67, 68]],
            ['Kawsu', 'Marena', 'm', [53, 82, 71, 70]],
            ['Joyce', 'Mensah', 'f', [78, 48, 67, 64]],
            ['Fatou', 'Njie', 'f', [80, 74, 64, 74]],
        ];

        ['offering' => $offering, 'type' => $type] = $this->seedSitting('2024', 'Grade 5', $subjects, $pupils);
        $analysis = new NatAnalysis($offering, $type);
        $breakdowns = $analysis->breakdowns();

        $this->assertCount(4, $breakdowns);

        // English: 4 boys / 10 girls / 14 all; mastery 1 / 3 / 4; no fails.
        $this->assertSame(['n' => 4, 'mastery' => 1], $this->pick($this->group($breakdowns, 'English', 'Male')));
        $this->assertSame(['n' => 10, 'mastery' => 3], $this->pick($this->group($breakdowns, 'English', 'Female')));
        $english = $this->group($breakdowns, 'English', 'All');
        $this->assertSame(14, $english['n']);
        $this->assertSame(0, $english['fail']);
        $this->assertSame(14, $english['pass']);
        $this->assertEquals(76, $english['average']); // mean of the 14 English scores, rounded
        $this->assertSame(4, $english['mastery']);
        $this->assertEqualsWithDelta(4 / 14, $english['pct_mastery'], 0.0001);

        // Maths mastery All = 3 (21%); Science & S.E.S. mastery 0 (nobody reached 80).
        $this->assertSame(3, $this->group($breakdowns, 'Mathematics', 'All')['mastery']);
        $this->assertSame(0, $this->group($breakdowns, 'Science', 'All')['mastery']);
        $this->assertSame(0, $this->group($breakdowns, 'Social & Environmental Studies', 'All')['mastery']);

        // Pupil totals reconcile against the WAEC summary box: max 319, min 246.
        $rows = $analysis->studentRows();
        $this->assertCount(14, $rows);
        $this->assertSame(317, $rows->firstWhere('total', 317)['total']); // Foday Badjie 85+82+76+74
        $this->assertSame(319, $rows->max('total'));                       // Drammeh Fatoumatta
        $this->assertSame(246, $rows->min('total'));                       // Jah Mariama
        $this->assertSame(3975, $rows->sum('total'));                      // mean 283.9 ≈ 284
    }

    #[Test]
    public function it_computes_the_2025_grade_3_fail_counts(): void
    {
        // NAT 2025 Grade 3 — Int. Std is the only subject with fails (26 and 32).
        $subjects = ['English', 'Mathematics', 'Integrated Studies'];
        $pupils = [
            ['Aisha', 'Conteh', 'f', [67, 48, 26]],   // Int. Std fail (26)
            ['Muhammed', 'Jaw', 'm', [43, 42, 32]],   // Int. Std fail (32)
            ['Maimuna', 'Baldeh', 'f', [94, 86, 84]],
            ['Ali', 'Jarra', 'm', [96, 92, 96]],
        ];

        ['offering' => $offering, 'type' => $type] = $this->seedSitting('2025', 'Grade 3', $subjects, $pupils);
        $breakdowns = (new NatAnalysis($offering, $type))->breakdowns();
        $intStd = $this->group($breakdowns, 'Integrated Studies', 'All');

        $this->assertSame(4, $intStd['n']);
        $this->assertSame(2, $intStd['fail']);
        $this->assertSame(2, $intStd['pass']);
        $this->assertEqualsWithDelta(0.5, $intStd['pct_fail'], 0.0001);
    }

    #[Test]
    public function absent_pupils_are_excluded_from_denominators(): void
    {
        $subjects = ['English'];
        $pupils = [
            ['Present', 'One', 'm', [80]],
            ['Absent', 'Two', 'f', [null]],   // absent / "A"
        ];

        ['offering' => $offering, 'type' => $type] = $this->seedSitting('2025', 'Grade 3', $subjects, $pupils);
        $english = $this->group(new NatAnalysis($offering, $type)->breakdowns(), 'English', 'All');

        $this->assertSame(1, $english['n']); // the absent pupil is not counted
        $this->assertSame(1, $english['mastery']);
    }

    #[Test]
    public function it_renders_the_scores_and_analysis_pdfs(): void
    {
        $subjects = ['English', 'Mathematics', 'Integrated Studies'];
        $pupils = [
            ['Aisha', 'Conteh', 'f', [67, 48, 26]],
            ['Ali', 'Jarra', 'm', [96, 92, 96]],
            ['Absent', 'Pupil', 'm', [null, null, null]],
        ];

        ['offering' => $offering] = $this->seedSitting('2025', 'Grade 3', $subjects, $pupils);

        $controller = new NatReportController();
        $request = \Illuminate\Http\Request::create('/');
        $this->assertStringStartsWith('%PDF', $controller->scores($request, $offering->id)->getContent(), 'scores did not produce a PDF');
        $this->assertStringStartsWith('%PDF', $controller->analysis($offering->id)->getContent(), 'analysis did not produce a PDF');
    }

    #[Test]
    public function the_scores_pdf_can_optionally_hide_fully_absent_pupils(): void
    {
        // one pupil sat, one was absent for every subject
        $subjects = ['English'];
        $pupils = [
            ['Present', 'One', 'm', [80]],
            ['Absent', 'Two', 'f', [null]],
        ];
        ['offering' => $offering, 'type' => $type] = $this->seedSitting('2025', 'Grade 3', $subjects, $pupils);

        // listing keeps both by default, drops the fully-absent one with hide_absent
        $this->assertCount(2, (new NatAnalysis($offering, $type))->studentRows());
        $kept = (new NatAnalysis($offering, $type))->studentRows()
            ->filter(fn ($r) => collect($r['scores'])->contains(fn ($v) => $v !== null))->values();
        $this->assertCount(1, $kept);
        $this->assertSame(80, $kept->first()['total']); // the present pupil remains

        $controller = new NatReportController();
        $this->assertStringStartsWith('%PDF', $controller->scores(\Illuminate\Http\Request::create('/', 'GET', ['hide_absent' => 1]), $offering->id)->getContent());
    }

    #[Test]
    public function thresholds_come_from_nat_grading_scale_bands(): void
    {
        $school = School::factory()->create();
        GradingScale::factory()->create(['school_id' => $school->id, 'purpose' => 'nat', 'label' => 'Pass', 'min_score' => 50, 'max_score' => 89.99]);
        GradingScale::factory()->create(['school_id' => $school->id, 'purpose' => 'nat', 'label' => 'Mastery', 'min_score' => 90, 'max_score' => 100]);

        // NAT analysis picks up the school's own thresholds (pass >= 50, mastery >= 90)
        $this->assertSame([50, 90], NatAnalysis::thresholdsForSchool($school));

        // ...but a term-report letter grade still resolves, ignoring the NAT bands.
        GradingScale::factory()->create(['school_id' => $school->id, 'label' => 'A', 'min_score' => 0, 'max_score' => 100, 'remark' => 'All']);
        $this->assertSame('A', GradingScale::resolve($school, 85)->label);
    }

    #[Test]
    public function thresholds_fall_back_to_defaults_without_bands(): void
    {
        $school = School::factory()->create();
        $this->assertSame([NatAnalysis::FAIL_BELOW, NatAnalysis::MASTERY_AT], NatAnalysis::thresholdsForSchool($school));
    }

    #[Test]
    public function nat_seeder_creates_type_subjects_and_bands(): void
    {
        $school = School::factory()->create();
        (new \Database\Seeders\NatSeeder())->run();

        $this->assertDatabaseHas('assessment_types', ['school_id' => $school->id, 'name' => 'National Assessment Test']);
        $this->assertDatabaseHas('subjects', ['name' => 'Integrated Studies']);
        $this->assertDatabaseHas('grading_scales', ['school_id' => $school->id, 'purpose' => 'nat', 'label' => 'Mastery']);

        $this->assertSame([40, 80], NatAnalysis::thresholdsForSchool($school->fresh()));
    }

    private function pick(array $stats): array
    {
        return ['n' => $stats['n'], 'mastery' => $stats['mastery']];
    }

    /**
     * The print routes themselves (not just the controller methods): the school's
     * actual NAT deliverables must come out as PDFs behind the staff gate.
     */
    #[Test]
    public function the_nat_print_routes_serve_pdfs_to_staff_and_403_pupils(): void
    {
        ['offering' => $offering] = $this->seedSitting('2025-2026', 'Grade 3', ['English'], [
            ['Awa', 'Jallow', 'f', [72]],
        ]);

        $scores = $this->actingAs($this->headmaster)->get(route('nat.print.scores', $offering->id));
        $scores->assertOk();
        $this->assertStringStartsWith('%PDF', $scores->getContent());

        $analysis = $this->actingAs($this->headmaster)->get(route('nat.print.analysis', $offering->id));
        $analysis->assertOk();
        $this->assertStringStartsWith('%PDF', $analysis->getContent());

        $this->actingAs($this->student)->get(route('nat.print.scores', $offering->id))->assertForbidden();
    }
}
