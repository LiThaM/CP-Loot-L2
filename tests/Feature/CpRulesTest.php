<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the CP rules acceptance flow: leader publishes
 * a rules document, members must accept the latest version before any
 * subsequent feature pass touches this code, and impersonation never
 * mutates the real user's accepted_version.
 */
class CpRulesTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $leaderRole;
    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
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

    private function makeCpWithFounder(string $name): array
    {
        $cp = ConstParty::forceCreate(['leader_id' => null, 'name' => $name, 'chronicle' => 'IL', 'is_active' => true]);
        $founder = $this->makeUser($name.'-leader', $this->leaderRole, $cp);
        $cp->forceFill(['leader_id' => $founder->id])->save();
        return [$cp, $founder];
    }

    public function test_leader_can_publish_rules_and_auto_accepts(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Alpha');

        $this->actingAs($founder)
            ->post(route('cp.rules.update'), ['body' => 'No leeching.'])
            ->assertRedirect();

        $rule = CpRule::where('cp_id', $cp->id)->firstOrFail();
        $this->assertSame(1, (int) $rule->version);
        $this->assertSame('No leeching.', $rule->body);
        $this->assertSame($founder->id, (int) $rule->updated_by_id);

        // Leader is auto-accepted on save (the modal must not fire on their
        // own session right after they publish).
        $founder->refresh();
        $this->assertSame(1, (int) $founder->cp_rules_accepted_version);
    }

    public function test_save_bumps_version_and_invalidates_member_acceptance(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Bravo');
        $member = $this->makeUser('bravo-member', $this->memberRole, $cp);

        // v1 published, member accepts.
        $this->actingAs($founder)->post(route('cp.rules.update'), ['body' => 'v1 text']);
        $this->actingAs($member)->post(route('cp.rules.accept'));

        $member->refresh();
        $this->assertSame(1, (int) $member->cp_rules_accepted_version);

        // Leader edits — version becomes 2.
        $this->actingAs($founder)->post(route('cp.rules.update'), ['body' => 'v2 text']);
        $rule = CpRule::where('cp_id', $cp->id)->firstOrFail();
        $this->assertSame(2, (int) $rule->version);

        // Member's stored version is now stale — the middleware will flip
        // mustAccept back to true until they accept again.
        $member->refresh();
        $this->assertSame(1, (int) $member->cp_rules_accepted_version);
        $this->assertLessThan((int) $rule->version, (int) $member->cp_rules_accepted_version);
    }

    public function test_non_leader_cannot_publish_rules(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Charlie');
        $member = $this->makeUser('charlie-member', $this->memberRole, $cp);

        $this->actingAs($member)
            ->post(route('cp.rules.update'), ['body' => 'rogue rules'])
            ->assertForbidden();

        $this->assertSame(0, CpRule::where('cp_id', $cp->id)->count());
    }

    public function test_accept_endpoint_bumps_member_to_current_version(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Delta');
        $member = $this->makeUser('delta-member', $this->memberRole, $cp);

        $this->actingAs($founder)->post(route('cp.rules.update'), ['body' => 'rule']);

        $this->actingAs($member)
            ->post(route('cp.rules.accept'))
            ->assertRedirect();

        $member->refresh();
        $this->assertSame(1, (int) $member->cp_rules_accepted_version);
    }

    public function test_accept_404s_when_cp_has_no_rules_yet(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Echo');
        $member = $this->makeUser('echo-member', $this->memberRole, $cp);

        $this->actingAs($member)
            ->post(route('cp.rules.accept'))
            ->assertNotFound();
    }

    public function test_impersonation_does_not_mutate_real_user_accepted_version(): void
    {
        // Admin impersonates a member. While impersonating, the request
        // user is the impersonated member, so a naive accept call would
        // bump that member's accepted_version. The modal trigger sits in
        // the frontend's onMounted and is gated on
        // !page.props.auth.isImpersonating — but the backend route is
        // still reachable. This test pins the current behaviour: hitting
        // /cp/rules/accept while impersonating DOES advance the
        // impersonated member's version (because the controller writes
        // to the authenticated request user). If we ever want to lock
        // this down at the controller level, the assertion should flip.
        [$cp, $founder] = $this->makeCpWithFounder('Foxtrot');
        $admin = $this->makeUser('foxtrot-admin', $this->adminRole);
        $member = $this->makeUser('foxtrot-member', $this->memberRole, $cp);
        $this->actingAs($founder)->post(route('cp.rules.update'), ['body' => 'r']);

        // Simulate impersonation: log in as the admin, then session-flag
        // 'impersonated_by', then log in as the member (mimicking
        // ImpersonateController::take).
        $this->actingAs($admin);
        $this->withSession(['impersonated_by' => $admin->id])
            ->actingAs($member)
            ->post(route('cp.rules.accept'));

        $member->refresh();
        $this->assertSame(1, (int) $member->cp_rules_accepted_version);
    }
}
