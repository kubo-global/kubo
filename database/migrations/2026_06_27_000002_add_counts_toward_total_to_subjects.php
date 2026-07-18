<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            // When false, the subject still appears on reports but its mark is not
            // summed into the term Total (the figure used to rank/position pupils).
            $table->boolean('counts_toward_total')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('counts_toward_total');
        });
    }
};
