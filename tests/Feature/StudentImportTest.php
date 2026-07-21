<?php

namespace Tests\Feature;

use App\Livewire\StudentImport;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulk student import: upload -> validate -> preview -> confirm. Nothing may be
 * written before confirmation, errors block the import entirely, and duplicates
 * (in-file and already-enrolled) are surfaced instead of silently doubling.
 */
class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private Offering $offering;

    public function setUp(): void
    {
        parent::setUp();
        School::factory()->create();
        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 1'])->id,
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('class.csv', $content);
    }

    #[Test]
    public function a_valid_list_previews_and_imports_on_confirm(): void
    {
        $before = Student::count();
        $roleBefore = \App\Models\User::role('student')->count();

        $component = Livewire::actingAs($this->headmaster)
            ->test(StudentImport::class)
            ->set('offeringId', $this->offering->id)
            ->set('file', $this->csv("first name,last name,gender\nSait,Camara,m\nOumie,Gaye,f\nBakary,Jaw,"));

        // Preview only: nothing written yet.
        $this->assertSame($before, Student::count());
        $component->assertSee('3 to add');

        $component->call('confirm')->assertSet('imported', true)->assertSet('importedCount', 3);

        $this->assertSame($before + 3, Student::count());
        $this->assertSame(3, Enrollment::where('offering_id', $this->offering->id)->count());
        $sait = Student::where('first_name', 'Sait')->where('last_name', 'Camara')->first();
        $this->assertSame('M', $sait->profile?->gender); // Profile normalises gender to uppercase

        // The role must hold on the USER morph: the pupil login gate checks
        // hasRole on a User instance, and a Student-typed pivot row misses it.
        $asUser = \App\Models\User::find($sait->id);
        $this->assertTrue($asUser->hasRole('student'));
        $this->assertSame($roleBefore + 3, \App\Models\User::role('student')->count());
    }

    #[Test]
    public function errors_block_the_import_and_nothing_is_written(): void
    {
        $before = Student::count();

        $component = Livewire::actingAs($this->headmaster)
            ->test(StudentImport::class)
            ->set('offeringId', $this->offering->id)
            ->set('file', $this->csv("Sait,Camara\nOnlyfirstname,\nSait,Camara"));

        // One missing last name + one in-file duplicate.
        $component->assertSee('2 with problems')->assertSee('importing is disabled');
        $component->call('confirm');
        $this->assertSame($before, Student::count());
    }

    #[Test]
    public function pupils_already_enrolled_in_the_class_are_skipped(): void
    {
        $existing = Student::factory()->create(['first_name' => 'Sait', 'last_name' => 'Camara']);
        Enrollment::factory()->create(['user_id' => $existing->id, 'offering_id' => $this->offering->id]);
        $before = Student::count();

        Livewire::actingAs($this->headmaster)
            ->test(StudentImport::class)
            ->set('offeringId', $this->offering->id)
            ->set('file', $this->csv("Sait,Camara\nOumie,Gaye"))
            ->assertSee('1 already enrolled')
            ->call('confirm')
            ->assertSet('importedCount', 1);

        $this->assertSame($before + 1, Student::count());
        $this->assertSame(1, Student::where('first_name', 'Sait')->where('last_name', 'Camara')->count());
    }

    #[Test]
    public function the_import_screen_is_for_headmaster_and_admin_only(): void
    {
        $this->actingAs($this->headmaster)->get(route('students.import'))->assertOk();
        $this->actingAs($this->admin)->get(route('students.import'))->assertOk();
        $this->actingAs($this->teacher)->get(route('students.import'))->assertForbidden();
    }
}
