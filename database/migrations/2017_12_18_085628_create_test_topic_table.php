<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestTopicTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('test_topic', function (Blueprint $table) {
            $table->integer('test_id')->unsigned();
            $table->integer('topic_id')->unsigned();

            $table->foreign('test_id')->references('id')->on('tests');
            $table->foreign('topic_id')->references('id')->on('topics');

            $table->primary(['test_id','topic_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('test_topic');
    }
}
