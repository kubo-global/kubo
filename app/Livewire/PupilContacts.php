<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\User;
use Livewire\Component;

/**
 * A pupil's contacts, with the add form on the list itself. Adding one used to
 * mean leaving the page for a separate form; a school secretary typing in a
 * family's numbers shouldn't have to make that trip for every name.
 */
class PupilContacts extends Component
{
    public User $user;

    public bool $adding = false;

    public string $firstName = '';
    public string $lastName = '';
    public string $relation = '';
    public string $primaryPhone = '';

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    /** Staff only, matching who may manage a pupil's record elsewhere. */
    public function canManage(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['headmaster', 'admin', 'teacher']);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function start(): void
    {
        $this->authorizeManage();

        $this->resetForm();
        $this->adding = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate([
            'firstName' => 'required|string|max:191',
            'lastName' => 'required|string|max:191',
            'relation' => 'nullable|string|max:191',
            'primaryPhone' => 'nullable|string|max:191',
        ]);

        $contact = Contact::create([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'relation' => $this->relation ?: null,
            'primary_phone' => $this->primaryPhone ?: null,
        ]);

        $this->user->contacts()->save($contact);

        // Straight back to a blank card: adding a father usually means adding a
        // mother next.
        $this->resetForm();
        $this->adding = true;
        session()->flash('contact-saved', $contact->first_name.' '.$contact->last_name.' added.');
    }

    private function resetForm(): void
    {
        $this->reset(['adding', 'firstName', 'lastName', 'relation', 'primaryPhone']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.user.pupil-contacts', [
            'contacts' => $this->user->contacts()->get(),
            'canManage' => $this->canManage(),
        ]);
    }
}
