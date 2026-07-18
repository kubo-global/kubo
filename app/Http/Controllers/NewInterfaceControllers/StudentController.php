<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Schoolyear;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $schoolyear = Schoolyear::latest();
        return view('pages.students',['title' => 'My Students'.' '.$schoolyear->name]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('pages.user',[
            'user' => null,
            'route' => 'students',
            'linkTitle' => 'Back',
            'method' => 'newStudent'
            ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Student $student)
    {
        if($student->enrollments()->exists()){
            return view('pages.user',[
                'user' => $student,
                'route' => 'students.index',
                'linkTitle' => 'Back to students',
                'method' => ''
                ]);
        } else {
            return redirect('dashboard')->with('error', 'No enrollments found.');
        }
    }
}
