<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the public CP creation funnel: a visitor submits CP details + their
 * account credentials in ONE request, and ends up logged-in as the
 * `cp_leader` of a fully-formed CP. The old "create CP, send magic link,
 * wait for register" two-step is gone — it left orphan CPs when the
 * requester never followed up.
 */
class CpRegistrationFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'cp_name' => 'Test CP',
            'server' => 'Gamma',
            'chronicle' => 'LU4',
            'leader_name' => 'TestHero',
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'message' => null,
        ], $overrides);
    }

    public function test_happy_path_creates_cp_user_and_leader_link(): void
    {
        $response = $this->post(route('cp.requests.store'), $this->validPayload());

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'alice@example.test')->first();
        $this->assertNotNull($user, 'user was not created');

        $cp = ConstParty::where('name', 'Test CP')->first();
        $this->assertNotNull($cp, 'cp was not created');

        $this->assertSame($cp->id, $user->cp_id);
        $this->assertSame($user->id, $cp->leader_id);
        $this->assertSame('cp_leader', $user->role?->name);
        $this->assertSame('approved', $user->membership_status);
        $this->assertNotNull($cp->invite_code, 'invite code should still be generated for future members');

        $this->assertAuthenticatedAs($user);
    }

    public function test_audit_row_in_cp_requests_is_created(): void
    {
        $this->post(route('cp.requests.store'), $this->validPayload());

        $audit = CpRequest::where('cp_name', 'Test CP')->first();
        $this->assertNotNull($audit);
        $this->assertSame('approved', $audit->status);
        $this->assertSame('alice@example.test', $audit->contact_email);
    }

    public function test_duplicate_email_is_rejected_and_no_cp_created(): void
    {
        User::forceCreate([
            'name' => 'Existing',
            'email' => 'taken@example.test',
            'password' => bcrypt('x'),
            'membership_status' => 'approved',
        ]);

        $response = $this->post(route('cp.requests.store'), $this->validPayload([
            'email' => 'taken@example.test',
            'cp_name' => 'Should Not Exist',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertNull(ConstParty::where('name', 'Should Not Exist')->first(), 'CP should not be created on validation failure');
        $this->assertGuest();
    }

    public function test_password_must_be_confirmed(): void
    {
        $response = $this->post(route('cp.requests.store'), $this->validPayload([
            'password_confirmation' => 'different',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertNull(ConstParty::where('name', 'Test CP')->first());
        $this->assertGuest();
    }

    public function test_invalid_chronicle_is_rejected(): void
    {
        $response = $this->post(route('cp.requests.store'), $this->validPayload([
            'chronicle' => 'NotARealChronicle',
        ]));

        $response->assertSessionHasErrors('chronicle');
        $this->assertGuest();
    }
}
