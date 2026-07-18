<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Domain\Learning\Models\AuthoredExercise;
use App\Domain\Learning\Models\AuthoredQuestion;
use KuboKolibri\Services\ChannelGenerator;

class ExerciseController extends Controller
{
    public function index()
    {
        $exercises = AuthoredExercise::where('created_by', auth()->id())
            ->with(['subject', 'skill', 'questions'])
            ->latest()
            ->get();

        return view('pages.exercises.index', compact('exercises'));
    }

    public function store(Request $request)
    {
        $data = $this->validateExercise($request);
        $questions = $this->validateQuestions($request);

        $school = School::first();

        $exercise = AuthoredExercise::create([
            'school_id' => $school->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject_id' => $data['subject_id'],
            'skill_id' => $data['skill_id'] ?: null,
            'mastery_m' => $data['mastery_m'],
            'mastery_n' => $data['mastery_n'],
            'randomize' => $data['randomize'] ?? true,
            'created_by' => auth()->id(),
        ]);

        $this->saveQuestions($exercise, $questions);

        if ($request->boolean('publish')) {
            try {
                app(ChannelGenerator::class)->generate($school->id);
                return redirect()->route('exercises.index')
                    ->with('success', "Exercise \"{$exercise->title}\" saved and published to Kolibri.");
            } catch (\Throwable $e) {
                return redirect()->route('exercises.index')
                    ->with('success', "Exercise \"{$exercise->title}\" saved. Publish to Kolibri when the server is running.");
            }
        }

        return redirect()->route('exercises.index')
            ->with('success', "Exercise \"{$exercise->title}\" saved.");
    }

    public function update(Request $request, AuthoredExercise $exercise)
    {
        $data = $this->validateExercise($request);
        $questions = $this->validateQuestions($request);

        $exercise->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject_id' => $data['subject_id'],
            'skill_id' => $data['skill_id'] ?: null,
            'mastery_m' => $data['mastery_m'],
            'mastery_n' => $data['mastery_n'],
            'randomize' => $data['randomize'] ?? true,
        ]);

        $exercise->questions()->delete();
        $this->saveQuestions($exercise, $questions);

        if ($request->boolean('publish')) {
            try {
                app(ChannelGenerator::class)->generate($exercise->school_id);
                return redirect()->route('exercises.index')
                    ->with('success', "Exercise \"{$exercise->title}\" updated and published to Kolibri.");
            } catch (\Throwable $e) {
                return redirect()->route('exercises.index')
                    ->with('success', "Exercise \"{$exercise->title}\" updated. Publish to Kolibri when the server is running.");
            }
        }

        return redirect()->route('exercises.index')
            ->with('success', "Exercise \"{$exercise->title}\" updated.");
    }

    public function destroy(AuthoredExercise $exercise)
    {
        $title = $exercise->title;
        $schoolId = $exercise->school_id;
        $exercise->questions()->delete();
        $exercise->delete();

        try {
            app(ChannelGenerator::class)->generate($schoolId);
        } catch (\Throwable $e) {
            // Kolibri unavailable — exercise deleted from DB, channel will sync later
        }

        return redirect()->route('exercises.index')
            ->with('success', "Exercise \"{$title}\" deleted.");
    }

    private function validateExercise(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'subject_id' => 'required|exists:subjects,id',
            'skill_id' => 'nullable|exists:skills,id',
            'mastery_m' => 'required|integer|min:1|max:10',
            'mastery_n' => 'required|integer|min:1|max:20',
        ]);
    }

    private function validateQuestions(Request $request): array
    {
        $questions = json_decode($request->input('questions_json', '[]'), true);

        if (!is_array($questions) || count($questions) < 2) {
            abort(422, 'At least 2 questions are required.');
        }

        foreach ($questions as $i => $q) {
            if (empty(trim($q['question_text'] ?? ''))) {
                abort(422, "Question " . ($i + 1) . " has no text.");
            }

            if (($q['question_type'] ?? 'radio') === 'radio') {
                $choices = $q['choices'] ?? [];
                if (count($choices) < 2) {
                    abort(422, "Question " . ($i + 1) . " needs at least 2 choices.");
                }
                $hasCorrect = collect($choices)->contains(fn ($c) => !empty($c['correct']));
                if (!$hasCorrect) {
                    abort(422, "Question " . ($i + 1) . " has no correct answer marked.");
                }
            } else {
                if (empty(trim($q['correct_answer'] ?? ''))) {
                    abort(422, "Question " . ($i + 1) . " has no correct answer.");
                }
            }
        }

        return $questions;
    }

    private function saveQuestions(AuthoredExercise $exercise, array $questions): void
    {
        foreach ($questions as $i => $q) {
            AuthoredQuestion::create([
                'authored_exercise_id' => $exercise->id,
                'sort_order' => $i,
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'] ?? 'radio',
                'choices' => ($q['question_type'] ?? 'radio') === 'radio' ? ($q['choices'] ?? []) : null,
                'correct_answer' => $q['correct_answer'] ?? null,
                'hints' => !empty($q['hints']) ? $q['hints'] : null,
            ]);
        }
    }
}
