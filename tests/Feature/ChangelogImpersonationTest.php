<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pin the impersonation behaviour for the web-changelog acknowledgment
 * flow. The admin must not be able to bump the impersonated user's
 * `changelog_last_seen_at` — the real user must still see the modal
 * when they log in themselves.
 *
 * Mirrors the pattern in CpRulesTest::test_impersonation_does_not_mutate
 * for the CP-rules accept endpoint.
 */
class ChangelogImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
    }

    private function makeUser(string $name, Role $role, ?ConstParty $cp = null): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => strtolower($name).'@t.l',
            'password' => bcrypt('x'),
            'role_id' => $role->id,
            'cp_id' => $cp?->id,
            'membership_status' => 'approved',
        ]);
    }

    public function test_acknowledge_during_impersonation_does_not_bump_real_user_last_seen(): void
    {
        $admin = $this->makeUser('alice-admin', $this->adminRole);
        $member = $this->makeUser('alice-member', $this->memberRole);
        $this->assertNull($member->changelog_last_seen_at);

        // Simulate ImpersonateController::take: admin gets authenticated,
        // then session-flagged with `impersonated_by`, then the auth user
        // is swapped to the member.
        $this->actingAs($admin);
        $this->withSession(['impersonated_by' => $admin->id])
            ->actingAs($member)
            ->post(route('changelog.ack'));

        $member->refresh();
        $this->assertNull($member->changelog_last_seen_at);
    }

    public function test_visit_index_during_impersonation_does_not_bump_real_user_last_seen(): void
    {
        $admin = $this->makeUser('bob-admin', $this->adminRole);
        $member = $this->makeUser('bob-member', $this->memberRole);
        $this->assertNull($member->changelog_last_seen_at);

        $this->actingAs($admin);
        $this->withSession(['impersonated_by' => $admin->id])
            ->actingAs($member)
            ->get(route('changelog.index'))
            ->assertOk();

        $member->refresh();
        $this->assertNull($member->changelog_last_seen_at);
    }

    public function test_acknowledge_outside_impersonation_does_bump_real_user_last_seen(): void
    {
        $member = $this->makeUser('carol-member', $this->memberRole);
        $this->assertNull($member->changelog_last_seen_at);

        $this->actingAs($member)->post(route('changelog.ack'));

        $member->refresh();
        $this->assertNotNull($member->changelog_last_seen_at);
    }
}
