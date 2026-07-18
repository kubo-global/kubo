<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EditSubjectsMakeOfferingIdNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('subjects', function ( $table) {
                $table->dropForeign('subjects_offering_id_foreign');
            });
        }

        Schema::table('subjects', function ( $table) {
            $table->integer('offering_id')->nullable()->unsigned()->change();
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('offering_id')->references('id')->on('offerings');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('subjects', function ( $table) {
                $table->dropForeign('subjects_offering_id_foreign');
            });
        }

        Schema::table('subjects', function ( $table) {
            $table->integer('offering_id')->nullable(false)->unsigned()->change();
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('offering_id')->references('id')->on('offerings');
            }
        });
    }
}
