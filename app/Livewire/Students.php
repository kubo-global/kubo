<?php

namespace App\Livewire;

use App\Models\Schoolyear;
use Livewire\WithPagination;
use Livewire\Component;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Students extends Component
{
    use WithPagination;
    public $search;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $search = '%' . $this->search . '%';
        $schoolyear = Schoolyear::latest();
        $students = $this->fetchStudentsFor($schoolyear, Auth::user());

        return view('livewire.students', [
            'students' => $students->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', $search)
                      ->orWhere('last_name', 'LIKE', $search);
            })->paginate(10),
            'schoolyear' => $schoolyear
        ]);
    }

    public function show(Student $student)
    {
        return redirect()->route('students.show', $student);
    }

    public function fetchStudentsFor(Schoolyear $schoolyear, User $loggedInUser)
    {
        $students = Student::listing()->enrolledIn($schoolyear)->orderBy('grade_id', 'asc');
        
        $unfilteredStudents = clone $students;
        $nrOfStudents = $students->withTeacher($loggedInUser)->count();

        if ($nrOfStudents > 0) {
            return $students->withTeacher($loggedInUser);
        }

        return $unfilteredStudents;
    }
}
