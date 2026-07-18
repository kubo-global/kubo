<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // permission overview
        Permission::create(['name' => 'view students']);
        Permission::create(['name' => 'view all students']);
        Permission::create(['name' => 'view class']);
        Permission::create(['name' => 'view all classes']);
        // The grant_health_permission_to_admin_and_headmaster migration may
        // have already created this — coexist via firstOrCreate.
        Permission::firstOrCreate(['name' => 'view medical records', 'guard_name' => 'web']);
        Permission::create(['name' => 'create new class']);
        Permission::create(['name' => 'add new user']);
        Permission::create(['name' => 'assign roles']);
        Permission::create(['name' => 'edit student details']);
        Permission::create(['name' => 'add student contact']);
        Permission::create(['name' => 'edit student contact']);
        Permission::create(['name' => 'add enrollment']);
        Permission::create(['name' => 'delete enrollment']);

        // Granular admin permissions. Routes that used to be role-gated on
        // `headmaster|admin` are now permission-gated on these, so the
        // Settings → Permissions matrix can shift access without code.
        // firstOrCreate because the 2026_05_17 migration creates them too;
        // tests run both, prod only runs the migration.
        Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage backups', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage rollover', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage settings', 'guard_name' => 'web']);
        // The role × permission matrix itself — the superuser key, split
        // out from manage settings so not everyone who can open Settings
        // can rewrite access control.
        Permission::firstOrCreate(['name' => 'manage permissions', 'guard_name' => 'web']);

        Role::create(['name' => 'headmaster']);
        Role::create(['name' => 'teacher']);
        Role::create(['name' => 'caregiver']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'student']);
        // The add_coordinator_roles migration may have already created this
        // (it runs idempotently for legacy environments where the seeder
        // doesn't get re-run). The "coordinator" role is the headmaster — no
        // separate role needed; assistant_coordinator is its own role.
        Role::firstOrCreate(['name' => 'assistant_coordinator', 'guard_name' => 'web']);
        // system_admin — the technical/sysadmin counterpart to admin
        // (school administration). Created via migration too, but the
        // seeder owns the create here for fresh installs + tests.
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);

        //headmaster permissions
        $headmaster = Role::findByName('headmaster');
        $headmaster->givePermissionTo('view all students');
        $headmaster->givePermissionTo('view all classes');
        $headmaster->givePermissionTo('edit student details');
        $headmaster->givePermissionTo('add student contact');
        $headmaster->givePermissionTo('edit student contact');
        $headmaster->givePermissionTo('add enrollment');
        $headmaster->givePermissionTo('delete enrollment');
        $headmaster->givePermissionTo('view medical records');
        // manage permissions: headmaster gets it by default so a small
        // school works out of the box; can be unchecked for stricter setups.
        $headmaster->givePermissionTo(['manage users', 'manage settings', 'manage permissions']);

        //teacher permissions
        $teacher = Role::findByName('teacher');
        $teacher->givePermissionTo('view students');
        $teacher->givePermissionTo('view class');

        //caregiver permissions
        $caregiver = Role::findByName('caregiver');
        $caregiver->givePermissionTo('view medical records');

        // School administration: hires/manages staff (users), runs the
        // academic-structure tabs in Settings. Doesn't see health or run
        // backups/rollover by default.
        $admin = Role::findByName('admin');
        $admin->givePermissionTo('create new class');
        $admin->givePermissionTo('assign roles');
        $admin->givePermissionTo(['manage users', 'manage settings']);

        // Sysadmin: backups + rollover + system-level settings. Could be
        // the same person as admin, but the permissions are split so a
        // school can give the IT person just the technical bits.
        $systemAdmin = Role::findByName('system_admin');
        $systemAdmin->givePermissionTo(['manage backups', 'manage rollover', 'manage settings', 'manage permissions']);

        // Assistant coordinator: academic oversight via role-gated routes
        // (students, scorebook, reports, timetable, NAT). Health/medical access is
        // intentionally NOT role-wide — an admin grants "view medical records" to
        // specific assistant_coordinator users (see routes/web.php health group).
    }
}
