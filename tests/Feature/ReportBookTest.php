<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportBookTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(): Enrollment
    {
        School::factory()->create();
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id'      => Grade::factory()->create()->id,
        ]);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $offering->subjects($this->term->id)->save($subject, ['term_id' => $this->term->id]);

        $pupil = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
        return Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);
    }

    #[Test]
    public function the_report_book_pdf_downloads(): void
    {
        $enrollment = $this->enrollment();

        $res = $this->actingAs($this->headmaster)
            ->get(route('report-book.print', ['enrollmentId' => $enrollment->id]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    #[Test]
    public function the_report_book_shows_on_screen_with_a_pdf_link(): void
    {
        $enrollment = $this->enrollment();

        $res = $this->actingAs($this->headmaster)
            ->get(route('report-book.screen', ['enrollmentId' => $enrollment->id]));

        $res->assertOk();
        $this->assertStringContainsString('text/html', $res->headers->get('content-type'));
        $res->assertSee('REPORT BOOK', false)                       // same booklet layout as the PDF
            ->assertSee('Awa Dukureh')                              // pupil's name rendered
            ->assertSee(route('report-book.print', ['enrollmentId' => $enrollment->id]), false); // Download PDF link
    }

    #[Test]
    public function the_blank_report_book_pdf_downloads(): void
    {
        $enrollment = $this->enrollment();

        $res = $this->actingAs($this->headmaster)
            ->get(route('report-book.print', ['enrollmentId' => $enrollment->id, 'empty' => 1]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    #[Test]
    public function the_class_report_books_pdf_downloads(): void
    {
        $offering = $this->enrollment()->offering;

        $res = $this->actingAs($this->headmaster)
            ->get(route('report-book.print-for-class', $offering));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    #[Test]
    public function the_report_type_can_be_configured(): void
    {
        School::factory()->create();

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-report-type'), ['report_type' => 'book'])
            ->assertRedirect();

        $this->assertEquals('book', School::first()->config('report_type'));
    }

    #[Test]
    public function grade_bands_can_be_added_edited_and_removed(): void
    {
        School::factory()->create();

        $this->actingAs($this->headmaster)
            ->post(route('settings.store-grade-band'), ['label' => 'A', 'min_score' => 70, 'max_score' => 100, 'remark' => 'Excellent'])
            ->assertRedirect();
        $this->assertDatabaseHas('grading_scales', ['label' => 'A', 'purpose' => null]);

        $band = GradingScale::where('label', 'A')->first();
        $this->actingAs($this->headmaster)
            ->post(route('settings.update-grade-band', $band), ['label' => 'A', 'min_score' => 75, 'max_score' => 100, 'remark' => 'Top'])
            ->assertRedirect();
        $this->assertDatabaseHas('grading_scales', ['id' => $band->id, 'remark' => 'Top']);

        $this->actingAs($this->headmaster)
            ->delete(route('settings.destroy-grade-band', $band))
            ->assertRedirect();
        $this->assertDatabaseMissing('grading_scales', ['id' => $band->id]);
    }

    #[Test]
    public function a_plain_teacher_cannot_configure_grading(): void
    {
        School::factory()->create();

        $this->actingAs($this->teacher)
            ->post(route('settings.update-report-type'), ['report_type' => 'book'])
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->post(route('settings.store-grade-band'), ['label' => 'X', 'min_score' => 0, 'max_score' => 10])
            ->assertForbidden();
    }
}
