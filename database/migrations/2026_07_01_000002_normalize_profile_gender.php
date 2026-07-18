<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Collapse historical shapes ('m'/'f', 'male'/'female', 'M'/'F') to the
        // canonical single letter the Profile model now enforces on write. substr()
        // and upper() work on both MySQL and SQLite.
        DB::statement("UPDATE profiles SET gender = upper(substr(gender, 1, 1)) WHERE gender IS NOT NULL AND gender <> ''");
        DB::statement("UPDATE profiles SET gender = NULL WHERE gender IS NOT NULL AND gender NOT IN ('M', 'F', 'O')");
    }

    public function down(): void
    {
        // One-way normalisation; nothing to restore.
    }
};
