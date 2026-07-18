<?php

namespace Database\Seeders;

use App\Models\GradingScale;
use App\Models\Offering;
use App\Models\School;
use App\Models\SchoolConfig;
use App\Models\Schoolyear;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Swallow's academic configuration, applied on top of the DemoSeeder base:
 *   - subjects per grade band (Grade 1-3 vs 4-6), each Mark-added or Graded;
 *   - the A-F grade key, with different thresholds for Grade 1-3 and 4-6;
 *   - the expected instructional hours per weekday (5h15, Friday 3h30).
 *
 * Idempotent — safe to re-run. Runs at the end of DemoSeeder so `db:seed`
 * produces the full Swallow demo, and can also be run standalone:
 *   php artisan db:seed --class=Database\\Seeders\\SwallowConfigSeeder
 */
class SwallowConfigSeeder extends Seeder
{
    /** Graded (letter grade, not summed) in both bands. */
    private array $graded = ['Religious Knowledge', 'Physical Education', 'Art and Craft', 'Health', 'Information Technology'];

    /** name => Mark-added (true) / Graded (false). */
    private array $spec = [
        'Religious Knowledge' => false, 'Physical Education' => false, 'Art and Craft' => false,
        'Health' => false, 'Information Technology' => false, 'Quantitative' => false,
        'English Language' => true, 'Mathematics' => true, 'Integrated Studies' => true, 'Phonics' => true,
        'Spelling and Dictation' => true, 'Verbal Aptitude' => true, 'French' => true, 'Science' => true,
    ];

    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            return;
        }

        $this->subjects($school);
        $this->gradeKey($school);
        $this->expectedHours($school);

        // The Swallow works per term (Test 1 / Test 2 / Exam); public schools stay on months.
        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::SCOREBOOK_PERIOD_MODE],
            ['value' => 'tests'],
        );
    }

    private function subjects(School $school): void
    {
        // Normalise any demo subject names to the sheet's spelling (keeps their scores).
        $renames = [
            'Art and craft' => 'Art and Craft', 'English language' => 'English Language',
            'Integrated studies' => 'Integrated Studies', 'IRK' => 'Religious Knowledge',
        ];
        foreach ($renames as $old => $new) {
            $sub = Subject::where('school_id', $school->id)->whereRaw('LOWER(name) = ?', [strtolower($old)])->first();
            $collision = Subject::where('school_id', $school->id)->whereRaw('LOWER(name) = ?', [strtolower($new)])->when($sub, fn ($q) => $q->where('id', '!=', $sub->id))->exists();
            if ($sub && $sub->name !== $new && ! $collision) {
                $sub->update(['name' => $new]);
            }
        }

        foreach ($this->spec as $name => $counts) {
            Subject::firstOrCreate(['name' => $name, 'school_id' => $school->id], ['counts_toward_total' => $counts])
                ->update(['counts_toward_total' => $counts]);
        }
        $ids = Subject::where('school_id', $school->id)->pluck('id', 'name');

        $band13 = array_merge($this->graded, ['Quantitative', 'English Language', 'Mathematics', 'Integrated Studies', 'Phonics', 'Spelling and Dictation', 'Verbal Aptitude', 'French']);
        $band46 = array_merge($this->graded, ['English Language', 'Mathematics', 'Phonics', 'Spelling and Dictation', 'Verbal Aptitude', 'French', 'Science']);

        foreach (Schoolyear::all() as $sy) {
            $termIds = $sy->terms()->pluck('id');
            foreach (Offering::with('grade')->where('schoolyear_id', $sy->id)->get() as $off) {
                $gradeName = $off->grade->name ?? '';
                if (stripos($gradeName, 'nursery') !== false) {
                    continue; // Nursery isn't on the sheet
                }
                preg_match('/\d+/', $gradeName, $m);
                $n = $m ? (int) $m[0] : null;
                if ($n === null) {
                    continue;
                }
                $set = ($n >= 1 && $n <= 3) ? $band13 : (($n >= 4 && $n <= 6) ? $band46 : null);
                if (! $set) {
                    continue;
                }

                DB::table('subject_term_offering')->where('offering_id', $off->id)->delete();
                foreach ($set as $name) {
                    if (! isset($ids[$name])) {
                        continue;
                    }
                    foreach ($termIds as $tid) {
                        DB::table('subject_term_offering')->updateOrInsert(
                            ['subject_id' => $ids[$name], 'term_id' => $tid, 'offering_id' => $off->id], []
                        );
                    }
                }
            }
        }
    }

    private function gradeKey(School $school): void
    {
        $school->gradingScales()->whereNull('purpose')->delete();

        $sets = [
            [1, 3, [['A', 85, 100, 'An excellent performance. Keep it up'], ['B', 70, 84, 'A very good work done'], ['C', 60, 69, 'A good result'], ['D', 50, 59, 'A fairly good performance'], ['E', 40, 49, 'A fair performance'], ['F', 0, 39, 'Poor performance']]],
            [4, 6, [['A', 85, 100, 'Excellent'], ['B', 75, 84, 'Very good'], ['C', 60, 74, 'Good'], ['D', 50, 59, 'Fairly good'], ['E', 40, 49, 'Fair'], ['F', 0, 39, 'Fail']]],
        ];
        foreach ($sets as [$gmin, $gmax, $bands]) {
            $order = 1;
            foreach ($bands as [$label, $min, $max, $remark]) {
                GradingScale::create([
                    'school_id' => $school->id, 'purpose' => null, 'grade_min' => $gmin, 'grade_max' => $gmax,
                    'label' => $label, 'min_score' => $min, 'max_score' => $max, 'remark' => $remark, 'display_order' => $order++,
                ]);
            }
        }
    }

    private function expectedHours(School $school): void
    {
        // Mon-Thu 5h15 (5.25), Friday 3h30 (3.5).
        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::EXPECTED_INSTRUCTIONAL_HOURS],
            ['value' => json_encode([1 => 5.25, 2 => 5.25, 3 => 5.25, 4 => 5.25, 5 => 3.5])],
        );
    }
}
