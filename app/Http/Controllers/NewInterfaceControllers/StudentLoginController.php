<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentLoginController extends Controller
{
    /** Step 1: pick a grade (grades that run this school year). */
    public function selectGrade(Request $request)
    {
        // A sign-in screen is where a session begins, so it must not render inside
        // somebody else's: a signed-in visitor (a teacher, or a demo headmaster who
        // wants to see how a child signs in) is signed out on the way in.
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $schoolyear = Schoolyear::orderByDesc('id')->first();
        $grades = Offering::where('schoolyear_id', $schoolyear->id)
            ->with('grade')->get()
            ->map(fn ($o) => $o->grade)->filter()
            ->filter(fn ($g) => $g->student_login_enabled)
            ->unique('id')->sortBy('name')->values();

        return view('pages.student-login.select-grade', ['grades' => $grades]);
    }

    /** Step 2: pick a class — but skip straight to the pupils if the grade has only one. */
    public function selectClass(Grade $grade)
    {
        // A grade hidden from the student login can't be reached by deep link.
        if (!$grade->student_login_enabled) {
            return redirect()->route('student-login.select-grade');
        }

        $schoolyear = Schoolyear::orderByDesc('id')->first();
        $offerings = Offering::where('schoolyear_id', $schoolyear->id)
            ->where('grade_id', $grade->id)->with('grade')->get()
            ->sortBy('name')->values();

        if ($offerings->count() === 1) {
            return redirect()->route('student-login.select-student', $offerings->first());
        }

        return view('pages.student-login.select-class', [
            'grade'     => $grade,
            'offerings' => $offerings,
        ]);
    }

    public function selectStudent(Offering $offering)
    {
        if (!optional($offering->grade)->student_login_enabled) {
            return redirect()->route('student-login.select-grade');
        }

        $students = $offering->enrollments()
            ->with('student')
            ->get()
            ->map(fn ($e) => $e->student)
            ->filter()
            ->sortBy('first_name');

        // Back goes to the class list when the grade has sections, else to the grades.
        $sections = Offering::where('schoolyear_id', $offering->schoolyear_id)
            ->where('grade_id', $offering->grade_id)->count();

        return view('pages.student-login.select-student', [
            'offering'  => $offering,
            'students'  => $students,
            'back'      => $sections > 1 ? route('student-login.select-class', $offering->grade) : route('student-login.select-grade'),
            'backLabel' => $sections > 1 ? 'Back to classes' : 'Back to grades',
        ]);
    }

    public function login(User $student)
    {
        if (!$student->hasRole('student')) {
            abort(403);
        }

        Auth::login($student);

        return redirect()->route('learn.index');
    }
}
