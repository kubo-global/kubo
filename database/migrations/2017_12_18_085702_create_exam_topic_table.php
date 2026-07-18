<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExamTopicTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exam_topic', function (Blueprint $table) {
            $table->integer('exam_id')->unsigned();
            $table->integer('topic_id')->unsigned();

            $table->foreign('exam_id')->references('id')->on('exams');
            $table->foreign('topic_id')->references('id')->on('topics');

            $table->primary(['exam_id','topic_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_topic');
    }
}
