<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOfferingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('offerings', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('schoolyear_id')->unsigned();
            $table->integer('grade_id')->unsigned();
            $table->timestamps();

            $table->foreign('grade_id')->references('id')->on('grades');
            $table->foreign('schoolyear_id')->references('id')->on('schoolyears');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('offerings');
    }
}
