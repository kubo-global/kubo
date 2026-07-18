<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\MedicalNote;
use App\Models\Student;
use Illuminate\Http\Request;

class MedicalNoteController extends Controller
{
    public function create(Student $student)
    {
        return view('pages.health.note.form', [
            'student' => $student,
            'note' => new MedicalNote(['noted_on' => now()->toDateString()]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->input('student');
        $data['recorded_by'] = auth()->id();
        MedicalNote::create($data);

        return redirect(route('health.pupil', $data['user_id']))
            ->with('success', 'Note saved.');
    }

    public function edit(MedicalNote $note)
    {
        return view('pages.health.note.form', [
            'student' => $note->student,
            'note' => $note,
        ]);
    }

    public function update(Request $request, MedicalNote $note)
    {
        $note->update($this->validated($request));

        return redirect(route('health.pupil', $note->user_id))
            ->with('success', 'Note updated.');
    }

    public function destroy(MedicalNote $note)
    {
        $studentId = $note->user_id;
        $note->delete();

        return redirect(route('health.pupil', $studentId))
            ->with('success', 'Note deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'noted_on' => 'required|date',
            'content' => 'required|string',
            'location' => 'nullable|string|max:191',
            'temperature' => 'nullable|numeric|between:30,45',
        ]);
    }
}
