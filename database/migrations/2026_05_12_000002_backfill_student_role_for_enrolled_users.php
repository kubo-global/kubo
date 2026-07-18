<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Some users are enrolled (= they're students) but never had the spatie
 * `student` role assigned — likely created via an import path that
 * predates role assignment. StudentLoginController::login aborts 403 on
 * users that lack the role, so they can't log in.
 *
 * Backfill the role for every enrolled user missing it, and clear the
 * permission cache so the change takes effect immediately. Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'student')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$roleId) {
            // Fresh install before the seeder ran — nothing to backfill.
            return;
        }

        $modelType = 'App\\Models\\User';

        $missing = DB::table('users as u')
            ->join('enrollments as e', 'e.user_id', '=', 'u.id')
            ->whereNotExists(function ($q) use ($roleId, $modelType) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'u.id')
                    ->where('model_has_roles.model_type', $modelType)
                    ->where('model_has_roles.role_id', $roleId);
            })
            ->distinct()
            ->pluck('u.id');

        if ($missing->isEmpty()) {
            return;
        }

        DB::table('model_has_roles')->insert(
            $missing->map(fn ($id) => [
                'role_id' => $roleId,
                'model_type' => $modelType,
                'model_id' => $id,
            ])->all()
        );

        // Spatie caches the permission table; clear so the next request
        // sees the new role assignments without a php artisan cache:clear.
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Not reversible — we don't track which assignments this migration
        // added vs which already existed.
    }
};
