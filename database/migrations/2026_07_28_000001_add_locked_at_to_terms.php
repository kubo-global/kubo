<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            // When set, the term is manually closed (locked) regardless of its end
            // date. Null = follows the automatic end-of-term lock.
            $table->timestamp('locked_at')->nullable()->after('end');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });
    }
};
