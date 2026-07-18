<?php

namespace App\Livewire;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use App\Domain\Learning\Models\AuthoredExercise;
use App\Domain\Learning\Models\AuthoredQuestion;
use App\Domain\Learning\Models\Skill;
use KuboKolibri\Services\ChannelGenerator;
use Livewire\Component;

class ExerciseCreator extends Component
{
    public int $formstep = 0;

    // Step 1: Skill selection
    public ?int $skillId = null;
    public string $skillName = '';
    public string $subjectName = '';
    public string $gradeName = '';

    // Step 2: Questions
    public array $questions = [];
    public int $currentQuestion = 0;

    // Current question fields
    public string $questionText = '';
    public string $questionType = 'radio';
    public array $choices = [];
    public int $correctChoice = 0;
    public string $correctAnswer = '';

    // Editing existing exercise
    public ?int $exerciseId = null;
    public string $exerciseTitle = '';

    // Advanced settings (collapsible on review step)
    public string $description = '';
    public int $masteryM = 3;
    public int $masteryN = 5;
    public bool $randomize = true;

    public function mount(?int $exerciseId = null): void
    {
        if ($exerciseId) {
            $this->loadExercise($exerciseId);
        } else {
            $this->resetChoices();
        }
    }

    public function render()
    {
        $skills = collect();
        if ($this->formstep === 0) {
            $skills = Skill::with('grade')
                ->orderBy('grade_id')
                ->orderBy('level')
                ->get()
                ->groupBy(fn ($s) => $s->grade?->name ?? 'Other');
        }

        return view('livewire.exercise-creator', [
            'skillsByGrade' => $skills,
        ]);
    }

    // Step 1: Select skill

    public function selectSkill(int $skillId): void
    {
        $skill = Skill::with(['grade', 'subject'])->find($skillId);
        if (!$skill) {
            return;
        }

        $this->skillId = $skill->id;
        $this->skillName = $skill->name;
        $this->subjectName = $skill->subject?->name ?? '';
        $this->gradeName = $skill->grade?->name ?? '';
        $this->exerciseTitle = $skill->name;

        $this->formstep = 1;
    }

    // Step 2: Build questions

    public function saveCurrentQuestion(): void
    {
        if (empty(trim($this->questionText))) {
            return;
        }

        $question = [
            'question_text' => $this->questionText,
            'question_type' => $this->questionType,
        ];

        if ($this->questionType === 'radio') {
            $filledChoices = array_filter($this->choices, fn ($c) => !empty(trim($c)));
            if (count($filledChoices) < 2) {
                return;
            }
            $question['choices'] = [];
            foreach ($this->choices as $i => $text) {
                if (!empty(trim($text))) {
                    $question['choices'][] = [
                        'text' => $text,
                        'correct' => ($i === $this->correctChoice),
                    ];
                }
            }
        } else {
            if (empty(trim($this->correctAnswer))) {
                return;
            }
            $question['correct_answer'] = $this->correctAnswer;
        }

        if (isset($this->questions[$this->currentQuestion])) {
            $this->questions[$this->currentQuestion] = $question;
        } else {
            $this->questions[] = $question;
        }
    }

    public function nextQuestion(): void
    {
        $this->saveCurrentQuestion();

        $this->currentQuestion = count($this->questions);
        $this->resetQuestionFields();
    }

    public function editQuestion(int $index): void
    {
        if (!isset($this->questions[$index])) {
            return;
        }

        $this->currentQuestion = $index;
        $q = $this->questions[$index];
        $this->questionText = $q['question_text'];
        $this->questionType = $q['question_type'];

        if ($q['question_type'] === 'radio') {
            $this->choices = [];
            $this->correctChoice = 0;
            foreach ($q['choices'] as $i => $choice) {
                $this->choices[] = $choice['text'];
                if ($choice['correct']) {
                    $this->correctChoice = $i;
                }
            }
            // Pad to at least 3
            while (count($this->choices) < 3) {
                $this->choices[] = '';
            }
        } else {
            $this->correctAnswer = $q['correct_answer'] ?? '';
            $this->resetChoices();
        }
    }

    public function removeQuestion(int $index): void
    {
        if (!isset($this->questions[$index])) {
            return;
        }

        array_splice($this->questions, $index, 1);

        if ($this->currentQuestion >= count($this->questions)) {
            $this->currentQuestion = max(0, count($this->questions) - 1);
        }

        $this->resetQuestionFields();
    }

    public function goToReview(): void
    {
        $this->saveCurrentQuestion();

        if (count($this->questions) < 2) {
            session()->flash('error', 'Add at least 2 questions.');
            return;
        }

        $this->formstep = 2;
    }

    public function backToSkills(): void
    {
        $this->formstep = 0;
    }

    public function backToQuestions(): void
    {
        $this->formstep = 1;
        $this->currentQuestion = count($this->questions);
        $this->resetQuestionFields();
    }

    // Step 3: Save

    public function save()
    {
        if (count($this->questions) < 2) {
            session()->flash('error', 'Add at least 2 questions.');
            return;
        }

        $school = School::first();
        $skill = $this->skillId ? Skill::find($this->skillId) : null;

        if (!$school || !$skill) {
            session()->flash('error', 'Could not save. Please select a skill and try again.');
            return;
        }

        if ($this->exerciseId) {
            $exercise = AuthoredExercise::find($this->exerciseId);
            $exercise->update([
                'title' => $this->exerciseTitle,
                'description' => $this->description ?: null,
                'subject_id' => $skill->subject_id,
                'skill_id' => $skill->id,
                'mastery_m' => $this->masteryM,
                'mastery_n' => $this->masteryN,
                'randomize' => $this->randomize,
            ]);
            $exercise->questions()->delete();
        } else {
            $exercise = AuthoredExercise::create([
                'school_id' => $school->id,
                'title' => $this->exerciseTitle,
                'description' => $this->description ?: null,
                'subject_id' => $skill->subject_id,
                'skill_id' => $skill->id,
                'mastery_m' => $this->masteryM,
                'mastery_n' => $this->masteryN,
                'randomize' => $this->randomize,
                'created_by' => auth()->id(),
            ]);
        }

        foreach ($this->questions as $i => $q) {
            AuthoredQuestion::create([
                'authored_exercise_id' => $exercise->id,
                'sort_order' => $i,
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'],
                'choices' => $q['question_type'] === 'radio' ? $q['choices'] : null,
                'correct_answer' => $q['correct_answer'] ?? null,
            ]);
        }

        // Auto-publish (skip gracefully if Kolibri content path isn't available)
        try {
            app(ChannelGenerator::class)->generate($school->id);
            session()->flash('success', "Exercise \"{$this->exerciseTitle}\" saved and published.");
        } catch (\Throwable $e) {
            session()->flash('success', "Exercise \"{$this->exerciseTitle}\" saved. Publish to Kolibri when the server is running.");
        }

        return redirect()->route('exercises.index');
    }

    // Helpers

    private function resetQuestionFields(): void
    {
        $this->questionText = '';
        $this->questionType = 'radio';
        $this->correctChoice = 0;
        $this->correctAnswer = '';
        $this->resetChoices();
    }

    public function addChoice(): void
    {
        if (count($this->choices) < 4) {
            $this->choices[] = '';
        }
    }

    private function resetChoices(): void
    {
        $this->choices = ['', '', ''];
    }

    private function loadExercise(int $id): void
    {
        $exercise = AuthoredExercise::with(['questions', 'skill.grade', 'skill.subject'])->find($id);
        if (!$exercise) {
            return;
        }

        $this->exerciseId = $exercise->id;
        $this->exerciseTitle = $exercise->title;
        $this->description = $exercise->description ?? '';
        $this->masteryM = $exercise->mastery_m;
        $this->masteryN = $exercise->mastery_n;
        $this->randomize = $exercise->randomize;
        $this->skillId = $exercise->skill_id ?? 0;

        if ($exercise->skill) {
            $this->skillName = $exercise->skill->name;
            $this->subjectName = $exercise->skill->subject?->name ?? '';
            $this->gradeName = $exercise->skill->grade?->name ?? '';
        }

        foreach ($exercise->questions as $q) {
            $question = [
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
            ];

            if ($q->question_type === 'radio') {
                $question['choices'] = $q->choices ?? [];
            } else {
                $question['correct_answer'] = $q->correct_answer;
            }

            $this->questions[] = $question;
        }

        // Start on questions step
        $this->formstep = 1;
        $this->currentQuestion = count($this->questions);
        $this->resetQuestionFields();
    }
}
