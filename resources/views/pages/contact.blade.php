<x-page :title="!is_null($contact) ? 'Contact | '.$contact->first_name.' '.$contact->last_name : 'Create new contact'">
    @livewire('contact',['contact'=>$contact,'method'=>$method,'student'=>$student])
</x-page>