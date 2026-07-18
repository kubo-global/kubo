<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsLogoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_school_logo_can_be_uploaded_in_settings(): void
    {
        School::factory()->create(['logo_path' => null]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-logo'), ['logo' => UploadedFile::fake()->image('crest.png', 200, 200)])
            ->assertRedirect();

        $path = School::first()->logo_path;
        $this->assertStringStartsWith('logos/', $path);
        $this->assertFileExists(public_path($path));

        @unlink(public_path($path)); // don't leave the test artifact behind
    }

    #[Test]
    public function a_non_image_logo_upload_is_rejected(): void
    {
        School::factory()->create(['logo_path' => null]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-logo'), ['logo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('logo');

        $this->assertNull(School::first()->logo_path);
    }
}
