<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            // Whether pupils in this grade may sign in through the student-login
            // flow. Off for grades too young to log themselves in (e.g. Nursery).
            $table->boolean('student_login_enabled')->default(true)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('student_login_enabled');
        });
    }
};
