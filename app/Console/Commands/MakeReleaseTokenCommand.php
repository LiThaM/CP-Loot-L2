<?php

namespace App\Console\Commands;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Console\Command;

class MakeReleaseTokenCommand extends Command
{
    protected $signature = 'releases:make-token
                            {email : Email of the admin user that will own the token}
                            {--name=ci-release-upload : Friendly token label shown in /profile}';

    protected $description = 'Generate a Sanctum personal access token with ability release:upload, for use by the build/publish script.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("No user found with email '{$email}'.");
            return self::FAILURE;
        }

        $adminRoleId = Role::where('name', 'admin')->value('id');
        if ($adminRoleId && $user->role_id !== $adminRoleId) {
            $this->warn("User '{$email}' is not an admin. Token will still be issued but the endpoint may not authorize it depending on your future policy.");
        }

        $token = $user->createToken($name, ['release:upload'])->plainTextToken;

        $this->newLine();
        $this->info('Token created. Store it as ADENALEDGER_RELEASE_TOKEN in the machine that runs the build script.');
        $this->line('  '.$token);
        $this->newLine();
        $this->comment('This value will NOT be shown again. Revoke it from the user panel if leaked.');

        return self::SUCCESS;
    }
}
