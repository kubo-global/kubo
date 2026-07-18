<?php

namespace Tests\Feature;

use App\Livewire\PupilContacts;
use App\Models\Contact;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contacts are added on the list itself: type the name where the card will be,
 * save, and the next blank card is already waiting.
 */
class PupilContactsTest extends TestCase
{
    use RefreshDatabase;

    private Student $pupil;

    public function setUp(): void
    {
        parent::setUp();
        $this->pupil = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
    }

    #[Test]
    public function a_contact_is_added_from_the_card_and_appears_at_once(): void
    {
        Livewire::actingAs($this->headmaster)
            ->test(PupilContacts::class, ['user' => $this->pupil])
            ->assertSee('Add a contact')
            ->call('start')
            ->set('firstName', 'Fatou')
            ->set('lastName', 'Dukureh')
            ->set('relation', 'Mother')
            ->set('primaryPhone', '7123456')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Fatou')            // in the list, straight away
            ->assertSee('Mother')
            ->assertSet('adding', true)     // and a blank card is ready for the next one
            ->assertSet('firstName', '');

        $contact = Contact::sole();
        $this->assertSame('7123456', $contact->primary_phone);
        $this->assertTrue($this->pupil->contacts()->where('contacts.id', $contact->id)->exists());
    }

    #[Test]
    public function a_contact_needs_a_name(): void
    {
        Livewire::actingAs($this->headmaster)
            ->test(PupilContacts::class, ['user' => $this->pupil])
            ->call('start')
            ->set('relation', 'Mother')
            ->call('save')
            ->assertHasErrors(['firstName', 'lastName']);

        $this->assertSame(0, Contact::count());
    }

    #[Test]
    public function a_caregiver_cannot_add_contacts(): void
    {
        $caregiver = \App\Models\User::create([
            'first_name' => 'Care', 'last_name' => 'Giver', 'password' => bcrypt('x'),
        ]);
        $caregiver->assignRole('caregiver');

        Livewire::actingAs($caregiver)
            ->test(PupilContacts::class, ['user' => $this->pupil])
            ->call('start')
            ->assertStatus(403);
    }
}
