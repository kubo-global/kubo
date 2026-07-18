<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Models\Contact;
use App\Http\Controllers\Controller;

use App\Models\Student;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Student $student)
    {
        return view('pages.contact',[
            'contact' => null,
            'student' => $student,
            'linkTitle' => 'Back',
            'method' => 'newContact'
            ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Student $student, Contact $contact)
    {
        return view('pages.contact', [
            'contact' => $contact,
            'student' => $student,
            'linkTitle' => 'Back',
            'method' => '',
        ]);
    }
}
