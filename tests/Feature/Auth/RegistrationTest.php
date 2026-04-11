<?php

namespace Tests\Feature\Auth;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Role::create([
            'name' => 'cp_leader',
            'display_name' => 'CP Leader',
            'description' => 'Leader of a Const Party',
        ]);

        $cp = ConstParty::create([
            'leader_id' => null,
            'name' => 'Test CP',
            'invite_code' => 'INVITE-TEST',
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_code' => $cp->invite_code,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }
}
