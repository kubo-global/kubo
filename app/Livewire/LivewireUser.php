<?php

namespace App\Livewire;

use App\Models\Enrollment;
use App\Models\Offering;
use Livewire\Component;
use App\Models\Student;
use App\Models\Profile;
use App\Models\Schoolyear;

class LivewireUser extends Component
{
    public $section = "profile";
    public $allowEditing = false;
    public $user;
    public $profile;
    public $method;
    public $offerings;
    public $selectedOffering;
    public $username;

    // Scalar bindings for the edit form. Livewire 3 serializes Eloquent models
    // as references (class + key, no attribute data), so wire:model="user.X"
    // hydrates as null on the client and wipes the input. Bind to these scalars
    // instead and sync back to the models on save.
    public string $firstName = '';
    public string $lastName = '';
    public string $gender = '';
    public string $birthDate = '';
    public string $tribe = '';
    public string $primaryPhone = '';
    public string $address = '';
    public string $comment = '';

    // Rules tuned to match prod data shape: strict on format, loose on
    // presence. Only first_name and last_name are required — every other
    // field has meaningful gaps in production (gender ~13% null, birth date
    // ~26%, tribe ~27%, phone ~63%, address ~68%) and forcing them would
    // block legitimate edits of incomplete records.
    protected $rules = [
        'firstName' => 'required|string|max:100',
        'lastName' => 'required|string|max:100',
        'gender' => 'nullable|in:F,M,Other',
        'birthDate' => 'nullable|date|before:today',
        'tribe' => 'nullable|string|max:100',
        'primaryPhone' => 'nullable|string|max:30',
        'address' => 'nullable|string|max:500',
        'comment' => 'nullable|string|max:2000',
        'selectedOffering' => 'nullable|integer|exists:offerings,id',
    ];

    // For new students we additionally require an offering and a gender —
    // both knowable at registration time. Validated in enrollStudent().
    protected array $newStudentRules = [
        'selectedOffering' => 'required|integer|exists:offerings,id',
        'gender' => 'required|in:F,M,Other',
        'birthDate' => 'required|date|before:today',
    ];

    public function mount($user, $method)
    {
        $this->method = $method;

        if ($method === 'newStudent') {
            $this->newStudentForm();
        } else {
            $this->username = $user->first_name . ' ' . $user->last_name;
            $this->user = $user;
            $this->profile = $user->profile ?? $user->profile()->create();
            $this->syncScalarsFromModels();
        }
    }

    private function syncScalarsFromModels(): void
    {
        $this->firstName = (string) ($this->user->first_name ?? '');
        $this->lastName = (string) ($this->user->last_name ?? '');
        $this->gender = (string) ($this->profile->gender ?? '');
        $birth = $this->profile->birth_date ?? null;
        $this->birthDate = $birth ? (is_string($birth) ? substr($birth, 0, 10) : $birth->format('Y-m-d')) : '';
        $this->tribe = (string) ($this->profile->tribe ?? '');
        $this->primaryPhone = (string) ($this->profile->primary_phone ?? '');
        $this->address = (string) ($this->profile->address ?? '');
        $this->comment = (string) ($this->profile->comment ?? '');
    }

    private function applyScalarsToModels(): void
    {
        $this->user->first_name = $this->firstName;
        $this->user->last_name = $this->lastName;
        $this->profile->gender = $this->gender;
        $this->profile->birth_date = $this->birthDate ?: null;
        $this->profile->tribe = $this->tribe;
        $this->profile->primary_phone = $this->primaryPhone;
        $this->profile->address = $this->address;
        $this->profile->comment = $this->comment;
    }

    public function render()
    {
        return view('livewire.user.form');
    }

    /**
     * Guard every state-changing action. Livewire methods are POST-invokable by
     * anyone who can render the component, and this one is rendered on /profile
     * (auth only) — so without this a student could drive staff-only writes
     * (edit records, self-enrol) against their own account. Managing user
     * records is staff work; match the students-management route's role set.
     */
    private function authorizeManage(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->hasAnyRole(['headmaster', 'admin', 'teacher']),
            403
        );
    }

    public function updateProfile()
    {
        $this->authorizeManage();
        $this->validate();
        $this->applyScalarsToModels();

        $this->user->save();
        $this->profile->save();
        $this->username = $this->user->first_name . ' ' . $this->user->last_name;

        $this->allowEditing = false;

        session()->flash('message', 'Details saved.');
    }

    public function enrollStudent()
    {
        $this->authorizeManage();
        $this->validate(array_merge($this->rules, $this->newStudentRules));
        $this->applyScalarsToModels();

        // Students don't have an email-based login — the seed password is
        // their birth date (matches the legacy admin flow). Caregivers can
        // reset later via /users.
        if (!$this->user->password) {
            $this->user->password = bcrypt($this->birthDate);
        }

        $this->user->save();
        $this->user->profile()->save($this->profile);

        Enrollment::create([
            'user_id' => $this->user->id,
            'offering_id' => $this->selectedOffering,
        ]);

        return redirect()->route('students.index');
    }

    public function showContact(Contact $contact)
    {
        return redirect()->route('users.contact', $contact);
    }


    public function makeProfileEditable()
    {
        $this->authorizeManage();
        $this->allowEditing = true;
    }

    public function cancelProfileEditing()
    {
        $this->allowEditing = false;
        $this->profile = $this->user->profile;
        $this->syncScalarsFromModels();
    }

    private function newStudentForm()
    {
        $currentSchoolyear = Schoolyear::current();
        if ($currentSchoolyear) {
            $this->offerings = Offering::where("schoolyear_id", "=", $currentSchoolyear->id)->get();
            $this->selectedOffering = $this->offerings->first()?->id;
        } else {
            $this->offerings = collect();
            $this->selectedOffering = null;
        }

        $student = new Student();
        $student->setRelation('profile', new Profile());

        $this->user = $student;
        $this->profile = $student->profile;
        $this->gender = 'F';
        $this->allowEditing = true;

        $this->section = 'new';
    }
}
