<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Learning\Models\AuthoredExercise;
use App\Domain\Learning\Models\AuthoredQuestion;
use App\Domain\Learning\Models\Skill;
use KuboKolibri\Services\ChannelGenerator;
use KuboKolibri\Services\PerseusGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class ExerciseAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;
    protected Subject $math;

    public function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->math = Subject::create(['name' => 'Mathematics']);
    }

    protected function createExerciseWithQuestions(array $exerciseOverrides = [], int $questionCount = 3): AuthoredExercise
    {
        $exercise = AuthoredExercise::create(array_merge([
            'school_id' => $this->school->id,
            'title' => 'Addition Facts',
            'subject_id' => $this->math->id,
            'mastery_m' => 3,
            'mastery_n' => 5,
            'randomize' => true,
            'created_by' => $this->teacher->id,
        ], $exerciseOverrides));

        for ($i = 0; $i < $questionCount; $i++) {
            AuthoredQuestion::create([
                'authored_exercise_id' => $exercise->id,
                'sort_order' => $i,
                'question_text' => "What is " . ($i + 1) . " + " . ($i + 2) . "?",
                'question_type' => 'radio',
                'choices' => [
                    ['text' => (string) (($i + 1) + ($i + 2)), 'correct' => true],
                    ['text' => (string) (($i + 1) + ($i + 2) + 1), 'correct' => false],
                    ['text' => (string) (($i + 1) + ($i + 2) - 1), 'correct' => false],
                ],
                'hints' => [['content' => 'Count on your fingers']],
            ]);
        }

        return $exercise->load('questions');
    }

    protected function questionsJson(array $questions = []): string
    {
        if (empty($questions)) {
            $questions = [
                [
                    'question_text' => 'What is 1 + 1?',
                    'question_type' => 'radio',
                    'choices' => [
                        ['text' => '2', 'correct' => true],
                        ['text' => '3', 'correct' => false],
                    ],
                    'correct_answer' => '',
                    'hints' => [],
                ],
                [
                    'question_text' => 'What is 2 + 2?',
                    'question_type' => 'radio',
                    'choices' => [
                        ['text' => '4', 'correct' => true],
                        ['text' => '5', 'correct' => false],
                    ],
                    'correct_answer' => '',
                    'hints' => [],
                ],
            ];
        }

        return json_encode($questions);
    }

    // ===================== PERSEUS GENERATOR =====================

    #[Test]
    public function test_perseus_generates_valid_zip()
    {
        $exercise = $this->createExerciseWithQuestions();
        $generator = new PerseusGenerator();

        $content = $generator->generate($exercise);

        $this->assertNotEmpty($content);

        // Write to temp file and verify it's a valid zip
        $tmp = tempnam(sys_get_temp_dir(), 'test_perseus_');
        file_put_contents($tmp, $content);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'Perseus output is a valid zip');

        // Must contain exercise.json
        $exerciseJson = $zip->getFromName('exercise.json');
        $this->assertNotFalse($exerciseJson);

        $exerciseData = json_decode($exerciseJson, true);
        $this->assertArrayHasKey('all_assessment_items', $exerciseData);
        $this->assertCount(3, $exerciseData['all_assessment_items']);

        // Each question has its own JSON file
        foreach ($exerciseData['all_assessment_items'] as $item) {
            $itemJson = $zip->getFromName($item['id'] . '.json');
            $this->assertNotFalse($itemJson, "Item file {$item['id']}.json exists");

            $itemData = json_decode($itemJson, true);
            $this->assertArrayHasKey('question', $itemData);
            $this->assertArrayHasKey('widgets', $itemData['question']);
            $this->assertArrayHasKey('hints', $itemData);
        }

        $zip->close();
        unlink($tmp);
    }

    #[Test]
    public function test_perseus_radio_question_has_correct_structure()
    {
        $exercise = $this->createExerciseWithQuestions([], 1);
        $generator = new PerseusGenerator();

        $content = $generator->generate($exercise);

        $tmp = tempnam(sys_get_temp_dir(), 'test_perseus_');
        file_put_contents($tmp, $content);

        $zip = new ZipArchive();
        $zip->open($tmp);

        $exerciseData = json_decode($zip->getFromName('exercise.json'), true);
        $itemId = $exerciseData['all_assessment_items'][0]['id'];
        $itemData = json_decode($zip->getFromName("{$itemId}.json"), true);

        // Check Perseus radio widget structure
        $widgets = $itemData['question']['widgets'];
        $this->assertArrayHasKey('radio 1', $widgets);

        $radio = $widgets['radio 1'];
        $this->assertEquals('radio', $radio['type']);
        $this->assertTrue($radio['graded']);
        $this->assertCount(3, $radio['options']['choices']);

        // First choice should be correct
        $this->assertTrue($radio['options']['choices'][0]['correct']);
        $this->assertFalse($radio['options']['choices'][1]['correct']);

        // Question content should reference the widget
        $this->assertStringContainsString('[[☃ radio 1]]', $itemData['question']['content']);

        // Hints present
        $this->assertCount(1, $itemData['hints']);
        $this->assertEquals('Count on your fingers', $itemData['hints'][0]['content']);

        $zip->close();
        unlink($tmp);
    }

    #[Test]
    public function test_perseus_numeric_input_question()
    {
        $exercise = AuthoredExercise::create([
            'school_id' => $this->school->id,
            'title' => 'Numeric Test',
            'subject_id' => $this->math->id,
            'mastery_m' => 3,
            'mastery_n' => 5,
            'created_by' => $this->teacher->id,
        ]);

        AuthoredQuestion::create([
            'authored_exercise_id' => $exercise->id,
            'sort_order' => 0,
            'question_text' => 'What is 5 + 3?',
            'question_type' => 'numeric_input',
            'correct_answer' => '8',
        ]);

        $exercise->load('questions');
        $generator = new PerseusGenerator();
        $content = $generator->generate($exercise);

        $tmp = tempnam(sys_get_temp_dir(), 'test_perseus_');
        file_put_contents($tmp, $content);

        $zip = new ZipArchive();
        $zip->open($tmp);

        $exerciseData = json_decode($zip->getFromName('exercise.json'), true);
        $itemId = $exerciseData['all_assessment_items'][0]['id'];
        $itemData = json_decode($zip->getFromName("{$itemId}.json"), true);

        $widgets = $itemData['question']['widgets'];
        $this->assertArrayHasKey('numeric-input 1', $widgets);
        $this->assertEquals(8.0, $widgets['numeric-input 1']['options']['answers'][0]['value']);
        $this->assertStringContainsString('[[☃ numeric-input 1]]', $itemData['question']['content']);

        $zip->close();
        unlink($tmp);
    }

    // ===================== CHANNEL GENERATOR =====================

    #[Test]
    public function test_channel_generator_creates_sqlite_with_correct_mptt()
    {
        $exercise = $this->createExerciseWithQuestions();
        $contentPath = sys_get_temp_dir() . '/kubo-test-kolibri-' . uniqid();

        $generator = new ChannelGenerator(new PerseusGenerator(), $contentPath);
        $result = $generator->generate($this->school->id);

        $this->assertNotNull($result['channel_id']);
        $this->assertEquals(1, $result['exercises']);

        // Verify SQLite database exists and has correct structure
        $dbPath = $result['db_path'];
        $this->assertFileExists($dbPath);

        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Channel metadata
        $channels = $pdo->query('SELECT * FROM content_channelmetadata')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(1, $channels);
        $this->assertEquals('KUBO School Exercises', $channels[0]['name']);

        // Content nodes: root + subject topic + exercise = 3
        $nodes = $pdo->query('SELECT * FROM content_contentnode ORDER BY lft')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertGreaterThanOrEqual(3, count($nodes));

        // Verify MPTT integrity: root lft=1, root rght = 2*count
        $root = $nodes[0];
        $this->assertEquals(1, $root['lft']);
        $this->assertEquals(0, $root['level']);
        $this->assertEquals('topic', $root['kind']);
        $this->assertEquals(count($nodes) * 2, $root['rght']);

        // Exercise node should be a leaf (rght = lft + 1)
        $exerciseNodes = array_filter($nodes, fn ($n) => $n['kind'] === 'exercise');
        foreach ($exerciseNodes as $en) {
            $this->assertEquals($en['lft'] + 1, $en['rght'], 'Exercise leaf node has rght = lft + 1');
        }

        // Assessment metadata
        $assessments = $pdo->query('SELECT * FROM content_assessmentmetadata')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(1, $assessments);

        $mastery = json_decode($assessments[0]['mastery_model'], true);
        $this->assertEquals('m_of_n', $mastery['type']);
        $this->assertEquals(3, $mastery['m']);
        $this->assertEquals(5, $mastery['n']);

        $itemIds = json_decode($assessments[0]['assessment_item_ids'], true);
        $this->assertCount(3, $itemIds);

        // Files
        $files = $pdo->query('SELECT * FROM content_file')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(1, $files);

        $localFiles = $pdo->query('SELECT * FROM content_localfile')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(1, $localFiles);
        $this->assertEquals('perseus', $localFiles[0]['extension']);

        // Clean up
        $this->cleanupDir($contentPath);
    }

    #[Test]
    public function test_channel_generator_writes_perseus_files_to_storage()
    {
        $this->createExerciseWithQuestions();
        $contentPath = sys_get_temp_dir() . '/kubo-test-kolibri-' . uniqid();

        $generator = new ChannelGenerator(new PerseusGenerator(), $contentPath);
        $generator->generate($this->school->id);

        // Find .perseus files in storage
        $found = glob("{$contentPath}/storage/*/*/*.perseus");
        $this->assertCount(1, $found);
        $this->assertGreaterThan(0, filesize($found[0]));

        $this->cleanupDir($contentPath);
    }

    #[Test]
    public function test_channel_generator_upserts_curriculum_map()
    {
        $exercise = $this->createExerciseWithQuestions();
        $contentPath = sys_get_temp_dir() . '/kubo-test-kolibri-' . uniqid();

        $generator = new ChannelGenerator(new PerseusGenerator(), $contentPath);
        $result = $generator->generate($this->school->id);

        $this->assertDatabaseHas('curriculum_maps', [
            'school_id' => $this->school->id,
            'subject_id' => $this->math->id,
            'content_kind' => 'exercise',
            'kolibri_channel_id' => $result['channel_id'],
        ]);

        $exercise->refresh();
        $this->assertNotNull($exercise->kolibri_node_id);
        $this->assertNotNull($exercise->kolibri_content_id);
        $this->assertNotNull($exercise->channel_synced_at);

        $this->cleanupDir($contentPath);
    }

    #[Test]
    public function test_channel_generator_links_skill_content()
    {
        $grade = Grade::factory()->create();
        $skill = Skill::create([
            'school_id' => $this->school->id,
            'subject_id' => $this->math->id,
            'grade_id' => $grade->id,
            'name' => 'Addition',
            'level' => 1,
        ]);

        $this->createExerciseWithQuestions(['skill_id' => $skill->id]);
        $contentPath = sys_get_temp_dir() . '/kubo-test-kolibri-' . uniqid();

        $generator = new ChannelGenerator(new PerseusGenerator(), $contentPath);
        $generator->generate($this->school->id);

        $this->assertDatabaseHas('skill_content', [
            'skill_id' => $skill->id,
            'role' => 'practice',
        ]);

        $this->cleanupDir($contentPath);
    }

    #[Test]
    public function test_channel_generator_returns_empty_when_no_exercises()
    {
        $contentPath = sys_get_temp_dir() . '/kubo-test-kolibri-' . uniqid();

        $generator = new ChannelGenerator(new PerseusGenerator(), $contentPath);
        $result = $generator->generate($this->school->id);

        $this->assertEquals(0, $result['exercises']);
        $this->assertNull($result['channel_id']);
    }

    // ===================== CONTROLLER / UI =====================

    #[Test]
    public function test_teacher_can_access_exercises_index()
    {
        $this->actingAs($this->teacher)
            ->get(route('exercises.index'))
            ->assertOk();
    }

    #[Test]
    public function test_student_cannot_access_exercises()
    {
        $this->actingAs($this->student)
            ->get(route('exercises.index'))
            ->assertForbidden();
    }

    #[Test]
    public function test_teacher_can_create_exercise()
    {
        // Mock ChannelGenerator to avoid filesystem side effects
        $this->mock(ChannelGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'exercises' => 1,
                'channel_id' => 'abc123',
                'db_path' => '/tmp/test.sqlite3',
            ]);
        });

        $this->actingAs($this->teacher)
            ->post(route('exercises.store'), [
                'title' => 'My First Exercise',
                'subject_id' => $this->math->id,
                'skill_id' => '',
                'mastery_m' => 3,
                'mastery_n' => 5,
                'randomize' => 1,
                'publish' => '1',
                'questions_json' => $this->questionsJson(),
            ])
            ->assertRedirect(route('exercises.index'));

        $this->assertDatabaseHas('authored_exercises', [
            'title' => 'My First Exercise',
            'subject_id' => $this->math->id,
            'created_by' => $this->teacher->id,
        ]);

        $this->assertDatabaseCount('authored_questions', 2);
    }

    #[Test]
    public function test_exercise_requires_at_least_two_questions()
    {
        $this->actingAs($this->teacher)
            ->post(route('exercises.store'), [
                'title' => 'Too Few Questions',
                'subject_id' => $this->math->id,
                'skill_id' => '',
                'mastery_m' => 3,
                'mastery_n' => 5,
                'questions_json' => json_encode([
                    ['question_text' => 'Only one', 'question_type' => 'radio', 'choices' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => false]], 'correct_answer' => '', 'hints' => []],
                ]),
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function test_teacher_can_update_exercise()
    {
        $exercise = $this->createExerciseWithQuestions();

        $this->mock(ChannelGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'exercises' => 1,
                'channel_id' => 'abc123',
                'db_path' => '/tmp/test.sqlite3',
            ]);
        });

        $this->actingAs($this->teacher)
            ->put(route('exercises.update', $exercise), [
                'title' => 'Updated Title',
                'subject_id' => $this->math->id,
                'skill_id' => '',
                'mastery_m' => 4,
                'mastery_n' => 6,
                'publish' => '1',
                'questions_json' => $this->questionsJson(),
            ])
            ->assertRedirect(route('exercises.index'));

        $exercise->refresh();
        $this->assertEquals('Updated Title', $exercise->title);
        $this->assertEquals(4, $exercise->mastery_m);
        // Old 3 questions replaced by new 2
        $this->assertEquals(2, $exercise->questions()->count());
    }

    #[Test]
    public function test_teacher_can_delete_exercise()
    {
        $exercise = $this->createExerciseWithQuestions();

        $this->mock(ChannelGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'exercises' => 0,
                'channel_id' => null,
            ]);
        });

        $this->actingAs($this->teacher)
            ->delete(route('exercises.destroy', $exercise))
            ->assertRedirect(route('exercises.index'));

        $this->assertDatabaseMissing('authored_exercises', ['id' => $exercise->id]);
        $this->assertDatabaseCount('authored_questions', 0);
    }

    private function cleanupDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $it = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($path);
    }
}
