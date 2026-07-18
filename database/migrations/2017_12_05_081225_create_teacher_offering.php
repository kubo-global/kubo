<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTeacherOffering extends Migration
{
    public function up()
    {
        Schema::create('teacher_offering', function (Blueprint $table) {
            $table->integer('user_id')->unsigned();
            $table->integer('offering_id')->unsigned();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('offering_id')->references('id')->on('offerings');

            $table->primary(['user_id','offering_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('teacher_offering');
    }
}
