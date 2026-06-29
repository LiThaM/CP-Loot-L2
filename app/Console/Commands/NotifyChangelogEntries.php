<?php

namespace App\Console\Commands;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Mail\ChangelogEntryPublished;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails every web changelog entry that hasn't been notified yet to the
 * opted-in CP leaders. Idempotent — once `notified_at` is set, the entry
 * is skipped on subsequent runs. Scheduled hourly from routes/console.php.
 *
 * Sync send (no queue) because no persistent `queue:work` is configured
 * in production. Switch to ->queue() if a worker is added later.
 */
class NotifyChangelogEntries extends Command
{
    protected $signature = 'changelog:notify
                            {--dry-run : Report what would be sent without sending or marking}';

    protected $description = 'Email pending web changelog entries to opted-in CP leaders.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $entries = ChangelogEntry::query()
            ->whereIn('audience', ['web', 'both'])
            ->whereNull('notified_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No pending changelog entries to notify.');
            return self::SUCCESS;
        }

        // NOTE: this app does not enforce email verification (MustVerifyEmail
        // is disabled on the User model), so `email_verified_at` is null for
        // everyone. The opt-in flag `changelog_emails_enabled` is the real
        // consent signal; gating on verification would silently drop every
        // recipient and no changelog email would ever go out.
        $leaders = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'cp_leader'))
            ->where('changelog_emails_enabled', true)
            ->get();

        if ($leaders->isEmpty()) {
            $this->warn('No opted-in verified CP leaders found — marking entries as notified anyway to avoid blocking the queue.');
            if (! $dryRun) {
                foreach ($entries as $entry) {
                    $entry->update(['notified_at' => now()]);
                }
            }
            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($entries as $entry) {
            $this->line("Processing entry #{$entry->id}: {$entry->title_es}");
            $sentForEntry = 0;
            $failedForEntry = 0;

            foreach ($leaders as $leader) {
                if ($dryRun) {
                    $this->line("  [dry-run] would send to {$leader->email}");
                    $sentForEntry++;
                    continue;
                }

                try {
                    Mail::to($leader->email)->send(new ChangelogEntryPublished($entry, $leader));
                    $sentForEntry++;
                } catch (Throwable $e) {
                    $failedForEntry++;
                    Log::warning('changelog:notify failed for leader', [
                        'entry_id' => $entry->id,
                        'leader_id' => $leader->id,
                        'leader_email' => $leader->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Only mark as notified if at least one mail went out; otherwise
            // leave it unnotified so the next tick retries.
            if (! $dryRun && $sentForEntry > 0) {
                $entry->update(['notified_at' => now()]);
            }

            $totalSent += $sentForEntry;
            $totalFailed += $failedForEntry;
            $this->line("  → sent: {$sentForEntry}, failed: {$failedForEntry}");
        }

        $entryCount = $entries->count();
        $leaderCount = $leaders->count();
        $this->info("Done. Entries: {$entryCount}, sent: {$totalSent}, failed: {$totalFailed}, leaders considered: {$leaderCount}.");

        return self::SUCCESS;
    }
}
