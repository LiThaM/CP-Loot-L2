<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Mail\ChangelogEntryPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyChangelogEntriesTest extends TestCase
{
    use RefreshDatabase;

    private Role $leaderRole;
    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $this->memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        // Seed migrations insert feature-announcement entries that survive
        // RefreshDatabase's per-test rollback because they happen during
        // migration, not the test. Wipe them so each test controls exactly
        // which entries the command sees.
        ChangelogEntry::query()->delete();
        Mail::fake();
    }

    private function makeLeader(array $overrides = []): User
    {
        $defaults = [
            'name' => 'leader-'.uniqid(),
            'email' => 'leader-'.uniqid().'@t.l',
            'password' => bcrypt('x'),
            'role_id' => $this->leaderRole->id,
            'membership_status' => 'approved',
            'email_verified_at' => now(),
            'changelog_emails_enabled' => true,
            'language_preference' => 'es',
        ];
        return User::forceCreate(array_merge($defaults, $overrides));
    }

    private function makeEntry(array $overrides = []): ChangelogEntry
    {
        $defaults = [
            'type' => 'feature',
            'audience' => 'web',
            'title_es' => 'Cambio ES',
            'title_en' => 'Change EN',
            'body_es' => '**hola**',
            'body_en' => '**hi**',
            'published_at' => now()->subMinute(),
            'notified_at' => null,
        ];
        return ChangelogEntry::create(array_merge($defaults, $overrides));
    }

    public function test_picks_only_pending_web_or_both_entries_published_in_the_past(): void
    {
        $this->makeLeader();

        $webUnnotified = $this->makeEntry(['audience' => 'web']);
        $webNotified = $this->makeEntry(['audience' => 'web', 'notified_at' => now()]);
        $desktopUnnotified = $this->makeEntry(['audience' => 'desktop']);
        $bothUnnotified = $this->makeEntry(['audience' => 'both']);
        $future = $this->makeEntry(['audience' => 'web', 'published_at' => now()->addHour()]);

        $this->artisan('changelog:notify')->assertSuccessful();

        // 2 entries × 1 leader = 2 mails (webUnnotified + bothUnnotified).
        Mail::assertSent(ChangelogEntryPublished::class, 2);
        $this->assertNotNull($webUnnotified->fresh()->notified_at);
        $this->assertNotNull($bothUnnotified->fresh()->notified_at);
        $this->assertNull($desktopUnnotified->fresh()->notified_at);
        $this->assertNull($future->fresh()->notified_at);
        $this->assertEquals(
            $webNotified->notified_at?->toIso8601String(),
            $webNotified->fresh()->notified_at?->toIso8601String(),
        );
    }

    public function test_only_opted_in_verified_cp_leaders_receive_mail(): void
    {
        $optedIn = $this->makeLeader(['email' => 'in@t.l']);
        $optedOut = $this->makeLeader(['email' => 'out@t.l', 'changelog_emails_enabled' => false]);
        $unverified = $this->makeLeader(['email' => 'unverified@t.l', 'email_verified_at' => null]);
        $member = User::forceCreate([
            'name' => 'memberguy',
            'email' => 'member@t.l',
            'password' => bcrypt('x'),
            'role_id' => $this->memberRole->id,
            'membership_status' => 'approved',
            'email_verified_at' => now(),
            'changelog_emails_enabled' => true,
        ]);

        $this->makeEntry();

        $this->artisan('changelog:notify')->assertSuccessful();

        Mail::assertSent(ChangelogEntryPublished::class, 1);
        Mail::assertSent(ChangelogEntryPublished::class, function ($mail) use ($optedIn) {
            return $mail->hasTo($optedIn->email);
        });
    }

    public function test_is_idempotent_on_consecutive_runs(): void
    {
        $this->makeLeader();
        $this->makeEntry();

        $this->artisan('changelog:notify')->assertSuccessful();
        Mail::assertSent(ChangelogEntryPublished::class, 1);

        $this->artisan('changelog:notify')->assertSuccessful();
        // Still exactly 1 — the second run should pick zero entries.
        Mail::assertSent(ChangelogEntryPublished::class, 1);
    }

    public function test_dry_run_does_not_send_or_mark(): void
    {
        $this->makeLeader();
        $entry = $this->makeEntry();

        $this->artisan('changelog:notify', ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($entry->fresh()->notified_at);
    }

    public function test_localizes_subject_to_recipient_language(): void
    {
        $this->makeLeader(['language_preference' => 'en', 'email' => 'en@t.l']);
        $this->makeLeader(['language_preference' => 'es', 'email' => 'es@t.l']);

        $this->makeEntry(['title_es' => 'Cosa nueva', 'title_en' => 'New thing']);

        $this->artisan('changelog:notify')->assertSuccessful();

        Mail::assertSent(ChangelogEntryPublished::class, function ($mail) {
            return $mail->hasTo('en@t.l') && str_contains($mail->envelope()->subject, 'New thing');
        });
        Mail::assertSent(ChangelogEntryPublished::class, function ($mail) {
            return $mail->hasTo('es@t.l') && str_contains($mail->envelope()->subject, 'Cosa nueva');
        });
    }

    public function test_with_no_leaders_marks_entries_to_avoid_blocking(): void
    {
        // No leaders created.
        $entry = $this->makeEntry();

        $this->artisan('changelog:notify')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNotNull($entry->fresh()->notified_at);
    }
}
