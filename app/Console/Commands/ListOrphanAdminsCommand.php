<?php

namespace App\Console\Commands;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Console\Command;

class ListOrphanAdminsCommand extends Command
{
    protected $signature = 'users:list-orphan-admins {--fix : Demote each match to cp_leader (DESTRUCTIVE — review the list first)}';
    protected $description = 'Lists users with role=admin AND cp_id != null — a pattern that usually means a CP leader escalated themselves to global admin via the (now patched) UserManagementController grietita.';

    public function handle(): int
    {
        $candidates = User::with('role', 'cp')
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->whereNotNull('cp_id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No orphan admins. Nothing to do.');
            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d users with role=admin AND cp_id != NULL:', $candidates->count()));
        $this->table(
            ['id', 'name', 'email', 'cp', 'is_cp_leader'],
            $candidates->map(fn ($u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->cp?->name ?? '—',
                $u->cp && $u->cp->leader_id === $u->id ? 'yes' : 'no',
            ])->all()
        );

        if (!$this->option('fix')) {
            $this->line('');
            $this->line('Re-run with --fix to demote them to cp_leader.');
            return self::SUCCESS;
        }

        $leaderRoleId = (int) \App\Contexts\Identity\Domain\Models\Role::where('name', 'cp_leader')->value('id');
        if (!$leaderRoleId) {
            $this->error('cp_leader role not found.');
            return self::FAILURE;
        }

        if (!$this->confirm('Demote all of the above to cp_leader?', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        foreach ($candidates as $u) {
            $u->update(['role_id' => $leaderRoleId]);
            $this->line(sprintf('  ✓ user_id=%d (%s) → cp_leader', $u->id, $u->email));
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
