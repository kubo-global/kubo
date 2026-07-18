<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveOfferingIdFromSubjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support dropping columns with foreign keys easily
            // Skip in SQLite (testing) - the column may not exist
            return;
        }
        
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('subjects_offering_id_foreign');
            }
            $table->dropColumn('offering_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->integer('offering_id')->unsigned();
            $table->foreign('offering_id')->references('id')->on('offerings')->onDelete('cascade');
        });
    }
}
