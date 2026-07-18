<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use KuboKolibri\Client\KolibriClient;
use KuboKolibri\Models\CurriculumMap;
use KuboKolibri\Services\KolibriProvisioner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for the Kolibri bridge security fixes (fix/kolibri-bridge-security).
 * The bridge is a KUBO module, so it's tested here against the real models, roles and
 * factories rather than in isolation. On the offline school LAN pupils hold valid
 * low-privilege accounts, so "a pupil cannot…" is the security-critical assertion.
 */
class KolibriBridgeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function mapping(): CurriculumMap
    {
        return CurriculumMap::create([
            'school_id'          => School::factory()->create()->id,
            'subject_id'         => Subject::factory()->create()->id,
            'kolibri_channel_id' => Str::uuid()->toString(),
            'kolibri_node_id'    => Str::uuid()->toString(),
            'content_kind'       => 'exercise',
            'display_order'      => 0,
        ]);
    }

    private function mappingPayload(): array
    {
        return [
            'school_id'          => School::factory()->create()->id,
            'subject_id'         => Subject::factory()->create()->id,
            'kolibri_channel_id' => Str::uuid()->toString(),
            'kolibri_node_id'    => Str::uuid()->toString(),
            'content_kind'       => 'exercise',
            'display_order'      => 0,
        ];
    }

    // --- Curriculum-mapping API is staff-only (the IDOR fix) ---

    #[Test]
    public function a_pupil_cannot_delete_a_curriculum_mapping(): void
    {
        $map = $this->mapping();

        $this->actingAs($this->student)
            ->deleteJson(route('kolibri.delete-mapping', $map->id))
            ->assertForbidden();

        $this->assertDatabaseHas('curriculum_maps', ['id' => $map->id]);
    }

    #[Test]
    public function a_pupil_cannot_create_a_curriculum_mapping(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('kolibri.create-mapping'), $this->mappingPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('curriculum_maps', 0);
    }

    #[Test]
    public function a_pupil_cannot_browse_the_kolibri_catalogue(): void
    {
        // Guarded before the controller ever resolves the (network-bound) client.
        $this->actingAs($this->student)
            ->getJson(route('kolibri.channels'))
            ->assertForbidden();
    }

    #[Test]
    public function a_teacher_can_delete_a_curriculum_mapping(): void
    {
        $map = $this->mapping();

        $this->actingAs($this->teacher)
            ->deleteJson(route('kolibri.delete-mapping', $map->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('curriculum_maps', ['id' => $map->id]);
    }

    #[Test]
    public function a_teacher_can_create_a_curriculum_mapping(): void
    {
        $this->actingAs($this->teacher)
            ->postJson(route('kolibri.create-mapping'), $this->mappingPayload())
            ->assertCreated();

        $this->assertDatabaseCount('curriculum_maps', 1);
        $this->assertDatabaseHas('curriculum_maps', ['mapped_by' => $this->teacher->id]);
    }

    // --- Learner passwords fail closed without a secret (the account-takeover fix) ---

    #[Test]
    public function provisioning_refuses_a_guessable_password_when_the_secret_is_unset(): void
    {
        $provisioner = $this->provisionerWithSecret('');

        $this->expectException(\RuntimeException::class);
        $provisioner->kolibriPassword($this->student);
    }

    #[Test]
    public function a_configured_secret_yields_a_deterministic_16_char_password(): void
    {
        $provisioner = $this->provisionerWithSecret('a-strong-random-value');

        $first = $provisioner->kolibriPassword($this->student);
        $again = $provisioner->kolibriPassword($this->student);

        $this->assertSame(16, strlen($first));
        $this->assertSame($first, $again);
    }

    /** Build a provisioner without the network-bound KolibriClient constructor. */
    private function provisionerWithSecret(string $secret): KolibriProvisioner
    {
        $ref = new \ReflectionClass(KolibriProvisioner::class);
        $provisioner = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('passwordSecret');
        $prop->setAccessible(true);
        $prop->setValue($provisioner, $secret);

        return $provisioner;
    }

    // --- Exercise cloning is not silently broken (the private-method fix) ---

    #[Test]
    public function the_kolibri_client_get_method_is_public_so_cloning_can_read_questions(): void
    {
        // PerseusReader::readQuestions() calls $client->get() from another class;
        // if it regresses to private, every clone-exercise silently returns [].
        $this->assertTrue(
            (new \ReflectionMethod(KolibriClient::class, 'get'))->isPublic()
        );
    }
}
