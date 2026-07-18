<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Domain\Learning\Models\AuthoredExercise;
use App\Domain\Learning\Models\AuthoredQuestion;
use App\Domain\Learning\Models\Skill;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use KuboKolibri\Services\PerseusReader;

class ContentBrowserController extends Controller
{
    /**
     * The exercise & video mapper — a Livewire workspace (see App\Livewire\CurriculumMapper)
     * for managing the curriculum-to-Kolibri content mapping in the UI.
     */
    public function index()
    {
        return view('pages.content.mapper');
    }

    /**
     * Clone a Kolibri exercise into an AuthoredExercise for editing.
     * Reads the Perseus data, creates KUBO records, redirects to the editor.
     */
    public function cloneExercise(Request $request, PerseusReader $reader)
    {
        $validated = $request->validate([
            'kolibri_node_id' => 'required|string',
            'title' => 'required|string',
            'skill_id' => 'required|exists:skills,id',
        ]);

        $questions = $reader->readQuestions($validated['kolibri_node_id']);

        $skill = Skill::find($validated['skill_id']);
        $school = School::first();

        $exercise = AuthoredExercise::create([
            'school_id' => $school->id,
            'title' => $validated['title'],
            'description' => 'Adapted from Kolibri exercise',
            'subject_id' => $skill->subject_id,
            'skill_id' => $skill->id,
            'mastery_m' => 3,
            'mastery_n' => 5,
            'randomize' => true,
            'created_by' => auth()->id(),
        ]);

        if (! empty($questions)) {
            foreach ($questions as $i => $q) {
                AuthoredQuestion::create([
                    'authored_exercise_id' => $exercise->id,
                    'sort_order' => $i,
                    'question_text' => $q['question_text'],
                    'question_type' => $q['question_type'],
                    'choices' => $q['choices'] ?? null,
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'hints' => $q['hints'] ?? null,
                ]);
            }
        }

        return redirect()->route('exercises.edit', $exercise->id)
            ->with('success', "Cloned \"{$validated['title']}\". Edit the questions below.");
    }
}
