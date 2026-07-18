<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Servicing access. Run on the school's machine (physical or SSH), this prints a
 * short-lived, signed sign-in link for a hidden "KUBO Support" account so a
 * technician can get in. There is no standing password on the login screen: the
 * only way in is to run this command on the box, and the link is signed with this
 * install's APP_KEY (so it works for this deployment only) and expires.
 */
class SupportAccess extends Command
{
    protected $signature = 'kubo:support {--minutes=15 : How long the sign-in link stays valid}';

    protected $description = 'Print a time-limited sign-in link for KUBO Support (run on the machine while servicing).';

    public function handle(): int
    {
        // Roles normally exist post-install; seed defensively so this never fails cold.
        if (! Role::where('name', 'system_admin')->where('guard_name', 'web')->exists()) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }

        // Created on demand (not a standing account until a technician needs it),
        // and visible in Users afterwards so the school can see and remove it.
        $user = User::firstOrCreate(
            ['first_name' => 'KUBO', 'last_name' => 'Support'],
            ['password' => bcrypt(Str::random(40)), 'archived' => false],
        );
        // system_admin keeps it off the login list; admin gives a working dashboard
        // and the user/settings access a technician needs.
        $user->syncRoles(['system_admin', 'admin']);

        $minutes = max(1, (int) $this->option('minutes'));
        $url = URL::temporarySignedRoute('support.login', now()->addMinutes($minutes), ['user' => $user->id]);

        $this->newLine();
        $this->info("KUBO Support sign-in link (valid {$minutes} min):");
        $this->line($url);
        $this->newLine();
        $this->comment('Open it in a browser on this machine or the school LAN. Remove the "KUBO Support" user in Users when you are done.');

        return self::SUCCESS;
    }
}
