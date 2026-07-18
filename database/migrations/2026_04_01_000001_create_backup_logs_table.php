<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method');           // 'manual', 'scheduled', 'external'
            $table->string('destination');       // 'download', 'usb', 'local', 'remote'
            $table->string('status');            // 'success', 'failed'
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('triggered_by')->nullable();
            $table->foreign('triggered_by')->references('id')->on('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
