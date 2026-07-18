<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Grade;
use App\Models\Schoolyear;
use App\Models\Offering;
use App\Models\SchoolParameter;
use App\Models\Term;
use Carbon\Carbon;

class BasicSchoolSeeder extends Seeder
{

    private $grades;
    private $schoolyears;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->grades = array();
        $this->schoolyears = array();
        $this->makeSchoolyears();
        $this->makeGrades();
        $this->makeOfferings();
        $this->makeTerms();
    }

    private function makeSchoolyears()
    {
        array_push($this->schoolyears,
            Schoolyear::create([
                "start" => '2018-08-16',
                "end" => '2019-08-31',
                "name" => '2018 - 2019'
            ])
        );

        array_push($this->schoolyears,
            Schoolyear::create([
                "start" => '2019-09-01',
                "end" => '2020-08-31',
                "name" => '2019 - 2020'
            ])
        );

        array_push($this->schoolyears,
            Schoolyear::create([
                "start" => '2020-09-01',
                "end" => '2021-08-31',
                "name" => '2020 - 2021'
            ])
        );

        array_push($this->schoolyears,
            Schoolyear::create([
                "start" => '2021-09-01',
                "end" => '2022-08-31',
                "name" => '2021 - 2022'
            ])
        );
    }

    private function makeGrades()
    {
        for ($x = 1; $x <= 3; $x++) {
            array_push($this->grades,
                Grade::create([
                    'name' => 'Nursery ' . $x
                ])
            );
        }

        for ($x = 1; $x <= 6; $x++) {
            array_push($this->grades,
                Grade::create([
                    'name' => 'Primary ' . $x
                ])
            );
        }
    }

    private function makeOfferings()
    {
        foreach ($this->schoolyears as $schoolyear) {
            foreach ($this->grades as $grade) {
                Offering::create([
                    'schoolyear_id' => $schoolyear->id,
                    'grade_id' => $grade->id,
                    'activated' => 1,
                    'name' => $schoolyear->name
                ]);
            }
        }
    }

    private function makeTerms()
    {
        Schoolyear::each(function($schoolyear) {
            Term::create([
                'schoolyear_id' => $schoolyear->id,
                'name' => 'Term 1',
                'start' => Carbon::create($schoolyear->start->year, 9, 01),
                'end' => Carbon::create($schoolyear->start->year, 12, 22)
            ]);
    
            Term::create([
                'schoolyear_id' => $schoolyear->id,
                'name' => 'Term 2',
                'start' => Carbon::create($schoolyear->start->year, 12, 22),
                'end' => Carbon::create($schoolyear->end->year, 3, 29)
            ]);
    
            Term::create([
                'schoolyear_id' => $schoolyear->id,
                'name' => 'Term 3',
                'start' => Carbon::create($schoolyear->end->year, 3, 29),
                'end' => Carbon::create($schoolyear->end->year, 8, 31)
            ]);
        });
    }
}
