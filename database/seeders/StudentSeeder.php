<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Profile;
use App\Models\Enrollment;
use App\Models\Offering;

class StudentSeeder extends Seeder
{
    public function run()
    {
        $offerings = Offering::all();

        foreach($offerings as $offering){
            $nrStudents = rand(5, 10);
            Student::factory($nrStudents)->create()->each(function ($student) use ($offering) {
                $student->assignRole('student');
                $this->createProfileAndEnrollment($student, $offering->id);
            });
        }
    }


    //Make profile + enroll in 3 grades
    private function createProfileAndEnrollment($student, $offering)
    {
        Profile::create([
            'user_id' => $student->id
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'offering_id'=> $offering
        ]);

        if ($offering < 9){

            Enrollment::create([
                'user_id' => $student->id,
                'offering_id'=> $offering+10
            ]);
        }


        if ($offering+10 < 18){

            Enrollment::create([
                'user_id' => $student->id,
                'offering_id'=> $offering+20
            ]);
        }

    }
}
