<?php

namespace App\Console\Commands;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Console\Command;

class ListOrphanAdminsCommand extends Command
{
    protected $signature = 'users:list-orphan-admins
                            {--fix : Walk the list interactively and ask for each user before demoting (no batch)}
                            {--exclude=* : Email(s) to exclude from the candidate list — e.g. legitimate admins who also lead a CP}';
    protected $description = 'Lists users with role=admin AND cp_id != null. Useful to find leaders who escalated themselves to admin via the patched grietita — but the pattern alone is NOT proof, so --fix asks per user before touching anything.';

    public function handle(): int
    {
        $exclude = collect((array) $this->option('exclude'))
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter()
            ->all();

        $candidates = User::with('role', 'cp')
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->whereNotNull('cp_id')
            ->when(!empty($exclude), fn ($q) => $q->whereNotIn('email', $exclude))
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

        $this->line('');
        $this->warn('REVIEW THIS LIST CAREFULLY. Legitimate global admins may also lead a CP — degrading them by accident locks them out of the admin panel.');

        if (!$this->option('fix')) {
            $this->line('Re-run with --fix to walk the list one user at a time. Use --exclude=email@x --exclude=email@y to skip known-good rows.');
            return self::SUCCESS;
        }

        $leaderRoleId = (int) \App\Contexts\Identity\Domain\Models\Role::where('name', 'cp_leader')->value('id');
        if (!$leaderRoleId) {
            $this->error('cp_leader role not found.');
            return self::FAILURE;
        }

        $demoted = 0;
        foreach ($candidates as $u) {
            $this->line('');
            $this->line(sprintf('user_id=%d  email=%s  cp=%s  is_cp_leader=%s',
                $u->id, $u->email, $u->cp?->name ?? '—',
                $u->cp && $u->cp->leader_id === $u->id ? 'yes' : 'no'
            ));
            if ($this->confirm('Demote THIS user to cp_leader?', false)) {
                $u->update(['role_id' => $leaderRoleId]);
                $this->line(sprintf('  ✓ user_id=%d → cp_leader', $u->id));
                $demoted++;
            } else {
                $this->line('  · skipped');
            }
        }

        $this->info(sprintf('Done. %d demoted, %d skipped.', $demoted, $candidates->count() - $demoted));
        return self::SUCCESS;
    }
}
