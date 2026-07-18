<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use \App\Models\User;
use \App\Models\Profile;

class UserSeeder extends Seeder
{
    public function run()
    {
        $user= User::create([
            'first_name' => env('ADMIN_FIRSTNAME', 'kubo'),
            'last_name' => env('ADMIN_LASTNAME', 'admin'),
            'email' => env('ADMIN_EMAIL', 'admin@kubo.rpi'),
            'password' => bcrypt(env('ADMIN_PASSWORD', 'secret'))
        ])->assignRole(['admin']);

        $user->profile()->save(new Profile([
            'birth_date' => '',
            'gender' => '',
            'primary_phone' => '',
            'address' => ''
        ]));

        #for ($x = 1; $x <= 9; $x++) {
        #    User::factory()->create()->each(function ($teacher) use ($x) {
        #        $teacher->assignRole('teacher');
        #        $this->createProfileAndAttachOffering($teacher, $x);
        #    });
        #}
    }

    #private function createProfileAndAttachOffering($teacher, $offering)
    #{
    #    Profile::create([
    #        'user_id' => $teacher->id
    #    ]);

    #    DB::table('teacher_offering')->insert([
    #        'user_id' => $teacher->id,
    #        'offering_id' => $offering
    #    ]);

    #    DB::table('teacher_offering')->insert([
    #        'user_id' => $teacher->id,
    #        'offering_id' => $offering+9
    #    ]);

    #    DB::table('teacher_offering')->insert([
    #        'user_id' => $teacher->id,
    #        'offering_id' => $offering+18
    #    ]);
    #}
}
