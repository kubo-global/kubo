<?php

use Illuminate\Database\Migrations\Migration;

class SeedSchoolParametersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('school_parameters')->insert([
            ['key' => 'examWeight', 'value' => 0.75],
            ['key' => 'testWeight', 'value' => 0.25]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
