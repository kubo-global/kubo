<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('health_reports', function (Blueprint $table) {
            $table->id();
            $table->string('general_condition')->nullable();
            $table->integer('height_in_cm')->nullable();
            $table->integer('weight_in_gram')->nullable();
            $table->string('teeth_condition')->nullable();
            $table->string('eyes_condition')->nullable();
            $table->string('ears_condition')->nullable();
            $table->string('hair_condition')->nullable();
            $table->string('nails_condition')->nullable();
            $table->string('wound_and_bruise_observations')->nullable();
            $table->boolean('worm_treatment_received')->nullable();
            $table->boolean('already_menstruated')->nullable();
            $table->boolean('hepatitis_a_vaccine_received')->nullable();
            $table->boolean('poliomyelitis_vaccine_received')->nullable();
            $table->boolean('tetanus_vaccine_received')->nullable();
            $table->boolean('yellow_fever_vaccine_received')->nullable();
            $table->integer('user_id')->unsigned();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('health_reports');
    }
};
