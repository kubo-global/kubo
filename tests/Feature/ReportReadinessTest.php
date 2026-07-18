<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\ReportReadiness;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportReadinessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_flags_a_subject_with_a_test_but_no_exam(): void
    {
        $school = School::first() ?? School::factory()->create();
        $test = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        $exam = AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 2'])->id,
        ]);
        $maths = Subject::factory()->create(['name' => 'Mathematics']);
        $english = Subject::factory()->create(['name' => 'English']);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);
        $offering->subjects($this->term->id)->save($english, ['term_id' => $this->term->id]);

        $pupil = Student::factory()->create();
        Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);

        // Maths: complete (test + exam). English: the exam was saved under the Test type.
        $mathTest = Assessment::factory()->create(['assessment_type_id' => $test->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);
        $mathExam = Assessment::factory()->create(['assessment_type_id' => $exam->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);
        $engTest = Assessment::factory()->create(['assessment_type_id' => $test->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $english->id, 'max_score' => 100]);

        AssessmentScore::factory()->create(['user_id' => $pupil->id, 'assessment_id' => $mathTest->id, 'score' => 80]);
        AssessmentScore::factory()->create(['user_id' => $pupil->id, 'assessment_id' => $mathExam->id, 'score' => 80]);
        AssessmentScore::factory()->create(['user_id' => $pupil->id, 'assessment_id' => $engTest->id, 'score' => 70]);

        $incomplete = (new ReportReadiness())->incompleteSubjects($offering, $this->term, $school);

        // English is flagged (has Test, missing Exam); Mathematics is not.
        $this->assertTrue($incomplete->contains(fn ($i) => $i['subject'] === 'English'));
        $this->assertFalse($incomplete->contains(fn ($i) => $i['subject'] === 'Mathematics'));

        $eng = $incomplete->firstWhere('subject', 'English');
        $this->assertSame(['Test'], $eng['has']);
        $this->assertSame(['Exam'], $eng['missing']);
    }

    #[Test]
    public function it_ignores_subjects_with_no_marks_at_all(): void
    {
        $school = School::first() ?? School::factory()->create();
        AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 2'])->id,
        ]);
        $art = Subject::factory()->create(['name' => 'Art']);
        $offering->subjects($this->term->id)->save($art, ['term_id' => $this->term->id]);

        $incomplete = (new ReportReadiness())->incompleteSubjects($offering, $this->term, $school);

        // A subject that hasn't started is not a problem, so it is not flagged.
        $this->assertFalse($incomplete->contains(fn ($i) => $i['subject'] === 'Art'));
    }
}
