<?php

namespace App\Livewire;

use App\Models\Contact as AppContact;
use Livewire\Component;

class Contact extends Component
{
    public $allowEditing=true;
    public $contact;
    public $method;
    public $username;
    public $student;
    public $prev;

    protected $rules = [
        'contact.first_name' => 'string',
        'contact.last_name' => 'string',
        'contact.gender' => 'string',
        'contact.tribe' => 'string',
        'contact.primary_phone' => 'string',
        'contact.address' => 'string',
        'contact.comment' => 'string',
    ];

    public function render()
    {
        return view('livewire.contact');
    }

    public function mount($contact,$method,$student){
        $this->method = $method;
        $this->prev = url()->previous();

        if($method === 'newContact'){
            $this->newContactForm($student);
        } else{
            // In the future this won't work, as users will be belongsToMany
            $this->student = $contact->users->first();
            $this->username = $contact->first_name.' '.$contact->last_name;
            $this->contact = $contact;
        } 
    }

    public function addContact(){
        $this->student->contacts()->save($this->contact);
        return redirect()->route('students.show', $this->student);
    }

    public function saveContact(){
        $this->contact->save();

        $this->username = $this->contact->first_name.' '.$this->contact->last_name;

        session()->flash('message', 'Contact saved.');
        return redirect()->route('students.show', $this->student);
    }

    public function deleteContact(){
        $this->contact->users()->detach();
        $this->contact->delete();
        return redirect($this->prev);
    }

    private function newContactForm($student){
        $contact = new AppContact();
        $this->student = $student;
        $this->contact = $contact;
    }
    
}
