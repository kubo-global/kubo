<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;
    public $search;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $search = '%'.$this->search.'%';
        $acceptedRoles = collect(["admin", "caregiver", "headmaster", "teacher"]);
        $users = User::role($acceptedRoles)->orderBy('first_name');

        return view('livewire.users', [
            'users' => $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', $search)
                      ->orWhere('last_name', 'LIKE', $search);
            })->paginate(10)
        ]);
    }

    public function show(User $user){
        return redirect()->route('users.user', $user);
    }
}
