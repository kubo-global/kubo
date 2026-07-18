{{-- The tab keeps the name (you may have several pupils open); the page header
     says what this page is, and the name leads the record itself. --}}
<x-page
    :title="!is_null($user) ? 'Student | '.$user->first_name.' '.$user->last_name : 'Create new student'"
    :heading="!is_null($user) ? 'Student record' : 'Create new student'">
    <livewire:livewire-user :user="$user" :method="$method" />
</x-page>
