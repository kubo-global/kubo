<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Profile;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentHealthMilestone;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bakoteh — teacher-workshop demo. Same school config as {@see BakotehSeeder}
 * (numeric grade key, report/assessment book, public-school calendar, Health
 * module off), but shaped for the hands-on session:
 *
 *  - Grade 1 with several classes (A, B, C), each with a scored roster.
 *  - One login per role so every seat can be demoed: headmaster Mr Jammeh,
 *    teacher Mr Jaiteh (principal of every Grade 1 class), plus admin,
 *    caregiver and assistant coordinator. All staff passwords: "bakoteh".
 *
 * Run on a FRESH, Bakoteh-only database (the app is single-school; School::first()
 * must be Bakoteh):
 *
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=RolesAndPermissionsSeeder
 *   php artisan db:seed --class=BakotehWorkshopSeeder
 */
class BakotehWorkshopSeeder extends BakotehSeeder
{
    private ?int $caregiverId = null;

    /** The workshop demoes Health, so keep the module on (parent ships it off). */
    protected function healthModuleEnabled(): bool
    {
        return true;
    }

    protected function headLastName(): string
    {
        return 'Jammeh';
    }

    protected function teacherLastNames(): array
    {
        return ['Jaiteh'];
    }

    /** Grade 1, three classes (A/B/C). Six calculated subjects + five graded. */
    protected function seedClasses(School $school, Schoolyear $year, array $terms): array
    {
        $subjects = [
            'English language', 'Mathematics', 'Integrated / S.E.S.', 'Science', 'Verbal Aptitude', 'Quantitative',
            'Religious Education', 'Arts', 'Spelling', 'Handwriting', 'Reading',
        ];
        $graded = ['Religious Education', 'Arts', 'Spelling', 'Handwriting', 'Reading'];
        $n = count($subjects);

        $a = [
            ['Modou', 'Ceesay', 'M'], ['Fatou', 'Jallow', 'F'], ['Lamin', 'Touray', 'M'], ['Awa', 'Sanneh', 'F'],
            ['Ebrima', 'Bojang', 'M'], ['Isatou', 'Darboe', 'F'], ['Ousman', 'Camara', 'M'], ['Mariama', 'Njie', 'F'],
            ['Sulayman', 'Fofana', 'M'], ['Binta', 'Sowe', 'F'], ['Alagie', 'Drammeh', 'M'], ['Adama', 'Manneh', 'F'],
        ];
        $b = [
            ['Momodou', 'Kinteh', 'M'], ['Haddy', 'Sonko', 'F'], ['Yankuba', 'Colley', 'M'], ['Jainaba', 'Sillah', 'F'],
            ['Bakary', 'Barrow', 'M'], ['Rohey', 'Conteh', 'F'], ['Cherno', 'Saidy', 'M'], ['Aminata', 'Gai', 'F'],
            ['Abdou', 'Ndoye', 'M'], ['Salla', 'Baye', 'F'], ['Amadou', 'Jobe', 'M'], ['Naffie', 'Traore', 'F'],
        ];
        $c = [
            ['Alhagie', 'Jarju', 'M'], ['Kumba', 'Jaw', 'F'], ['Malang', 'Saidy', 'M'], ['Isatou', 'Janneh', 'F'],
            ['Ousainou', 'Ndong', 'M'], ['Fatoumatta', 'Bah', 'F'], ['Musa', 'Sarr', 'M'], ['Mam', 'Jatta', 'F'],
            ['Pa', 'Mendy', 'M'], ['Sira', 'Nyang', 'F'], ['Kebba', 'Susso', 'M'], ['Nyima', 'Badjie', 'F'],
        ];

        return [
            $this->seedClass($school, $year, $terms, 'Grade 1', 'A', $subjects, $this->withScores($a, $n, 1), $graded),
            $this->seedClass($school, $year, $terms, 'Grade 1', 'B', $subjects, $this->withScores($b, $n, 2), $graded),
            $this->seedClass($school, $year, $terms, 'Grade 1', 'C', $subjects, $this->withScores($c, $n, 3), $graded),
        ];
    }

    /** Append a deterministic score (35-95) per subject to each [first, last, gender] row. */
    private function withScores(array $names, int $subjectCount, int $salt): array
    {
        $out = [];
        foreach ($names as $i => [$first, $last, $gender]) {
            $row = [$first, $last, $gender];
            for ($s = 0; $s < $subjectCount; $s++) {
                $row[] = 35 + ((($i + 1) * 7 + ($s + 1) * 13 + $salt * 5) % 61);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * All staff logins for the workshop: the head, Mr Jaiteh as principal of
     * every Grade 1 class, and one user for each remaining role so each can be
     * demoed. Students log in through their own picker, not this list.
     */
    protected function seedStaff(School $school, array $offerings): void
    {
        $make = function (string $last, string $role, string $title = 'Mr') use ($school) {
            $user = User::create([
                'first_name' => $title, 'last_name' => $last,
                'password' => bcrypt($this->password()), 'school_id' => $school->id, 'archived' => false,
            ]);
            $user->assignRole($role);

            return $user;
        };

        $make('Jammeh', 'headmaster');

        // Mr Jaiteh teaches Grade 1: make him principal of every section so all
        // three classes are "his" when he logs in.
        $jaiteh = $make('Jaiteh', 'teacher');
        foreach ($offerings as $offering) {
            DB::table('teacher_offering')->insert(['user_id' => $jaiteh->id, 'offering_id' => $offering->id, 'principal' => 1]);
        }

        $make('Ceesay', 'admin', 'Mrs');
        $this->caregiverId = $make('Touray', 'caregiver', 'Mrs')->id;
        $make('Sanyang', 'assistant_coordinator');
    }

    /**
     * Health demo data. Grade 1 pupils get believable ages, several years of
     * height/weight (so growth over time is visible), plus wound cases, incident
     * reports and vaccination milestones — all recorded by the caregiver.
     */
    protected function afterSeed(School $school, array $offerings): void
    {
        $ids = User::role('student')->orderBy('id')->pluck('id')->all();
        if (! $ids) {
            return;
        }
        $now = Carbon::now()->toDateTimeString();

        // Grade 1 pupils are ~7: give believable birth dates so the age reads right.
        foreach ($ids as $i => $uid) {
            Profile::where('user_id', $uid)->update(['birth_date' => Carbon::create(2018, 1 + ($i % 12), 1 + ($i % 27))]);
        }

        // The growth curves are sex-specific.
        $sexes = Profile::whereIn('user_id', $ids)->pluck('gender', 'user_id')->all();

        $conditions = ['Generally healthy', 'Mild cough, resolved', 'Occasional stomach pain', 'Healthy and active'];
        $cond = ['Good', 'Good', 'Excellent', 'Poor'];

        // A yearly check for every pupil from the year they turn five (before that
        // the WHO 2007 reference, and so the chart, doesn't cover them). Height and
        // weight hang off the WHO median: each pupil keeps their own offset and
        // grows along it, so the record shows a believable line rather than a dot.
        foreach ($ids as $i => $uid) {
            $birth = Carbon::create(2018, 1 + ($i % 12), 1 + ($i % 27));
            $zHeight = (($i % 7) - 3) / 4;   // -0.75 .. +0.75 SD
            $zBmi = (($i % 5) - 2) / 4;      // -0.50 .. +0.50 SD

            foreach ([2023, 2024, 2025, 2026] as $y => $year) {
                $on = Carbon::create($year, 10, 12);
                $ageMonths = $birth->diffInMonths($on);
                if ($ageMonths < 60) {
                    continue;
                }

                $wobble = (($i + $y) % 3 - 1) / 10; // a measurement is never exactly on the line
                $height = GrowthCurve::heightCm($sexes[$uid] ?? 'M', $ageMonths, $zHeight + $wobble);
                $weight = GrowthCurve::weightKg($sexes[$uid] ?? 'M', $ageMonths, $height, $zBmi + $wobble);

                DB::table('health_reports')->insert([
                    'user_id' => $uid, 'general_condition' => $conditions[($i + $y) % count($conditions)],
                    'height_in_cm' => (int) round($height),
                    'weight_in_gram' => (int) round($weight * 1000),
                    'teeth_condition' => $cond[($i + $y) % 4], 'eyes_condition' => $cond[$y % 4],
                    'ears_condition' => $cond[$i % 4], 'hair_condition' => 'Good', 'nails_condition' => $cond[$y % 4],
                    'wound_and_bruise_observations' => null, 'worm_treatment_received' => $y % 2,
                    'created_at' => $on->toDateTimeString(), 'updated_at' => $on->toDateTimeString(),
                ]);
            }
        }

        // Wound cases (+ follow-up visits), recorded by the caregiver.
        $diagnoses = ['Cut on the knee', 'Grazed elbow', 'Splinter in the foot', 'Small burn on the hand', 'Bruised shin', 'Insect bite, swollen'];
        $treatments = ['Cleaned, advised rest', 'Removed splinter, cleaned', 'Disinfected, plaster applied', 'Cleaned and bandaged'];
        $woundRemarks = ['Visit again in 2 days', 'Healing well'];
        foreach (range(0, 5) as $i) {
            $opened = Carbon::create(2026, 5 + ($i % 2), 3 + $i * 3);
            $caseId = DB::table('wound_cases')->insertGetId([
                'user_id' => $ids[$i % count($ids)], 'opened_on' => $opened->toDateString(),
                'diagnosis' => $diagnoses[$i % count($diagnoses)],
                'closed_on' => $i < 3 ? null : $opened->copy()->addDays(6)->toDateString(), // some still open
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (range(1, 1 + ($i % 2)) as $v) {
                DB::table('wound_care_visits')->insert([
                    'wound_case_id' => $caseId, 'recorded_by' => $this->caregiverId,
                    'visited_on' => $opened->copy()->addDays($v * 2)->toDateString(),
                    'treatment' => $treatments[($i + $v) % count($treatments)], 'remarks' => $woundRemarks[$v % 2],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Incident reports.
        $complaints = ['Fell during break and hurt the elbow', 'Headache and mild fever', 'Stomach ache after lunch', 'Twisted ankle playing football', 'Nosebleed in class', 'Bee sting on the arm'];
        $locations = ['Playground', 'Classroom', 'Football field', 'Corridor', 'Assembly hall'];
        $actions = ['Comforted and observed', 'Rested in the sick bay', 'Given water and rest', 'Wound cleaned and dressed'];
        $medications = ['Paracetamol 250 mg', 'ORS sachet', 'Antiseptic and a plaster'];
        foreach (range(0, 9) as $i) {
            $feverish = ($i % 3) === 0;
            $occurred = Carbon::create(2026, 5 + ($i % 2), 2 + $i * 2, 9 + ($i % 5), ($i * 7) % 60);
            $sentHome = ($i % 4) === 0 ? 1 : 0;
            $hospital = ($i % 9) === 0 ? 1 : 0;
            DB::table('incident_reports')->insert([
                'user_id' => $ids[($i * 3) % count($ids)], 'recorded_by' => $this->caregiverId,
                'occurred_at' => $occurred->toDateTimeString(),
                'location' => $locations[$i % count($locations)], 'temperature' => $feverish ? 37.5 + ($i % 5) / 10 : null,
                'complaint' => $complaints[$i % count($complaints)], 'action_taken' => $actions[$i % count($actions)],
                'first_aid_given' => $i % 2, 'sent_home' => $sentHome, 'taken_to_hospital' => $hospital,
                'medication_given' => $feverish ? $medications[$i % count($medications)] : null,
                'parents_contacted' => ($sentHome || $hospital) ? 1 : 0,
                'closed_on' => $i < 2 ? null : $occurred->copy()->addDay()->toDateString(), // two still open
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Vaccination milestones so that tab isn't empty either.
        foreach (array_slice($ids, 0, 8) as $i => $uid) {
            StudentHealthMilestone::updateOrCreate(['user_id' => $uid], [
                'polio_received_on' => Carbon::create(2019, 3, 10),
                'tetanus_received_on' => Carbon::create(2020, 6, 15),
                'yellow_fever_received_on' => ($i % 2) ? Carbon::create(2021, 9, 1) : null,
                'hep_a_received_on' => ($i % 3) ? Carbon::create(2021, 2, 20) : null,
            ]);
        }
    }
}
