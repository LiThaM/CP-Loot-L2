<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    // Regression: the /locale closure once resolved `Request` to the global
    // facade alias (its import lived further down in routes/web.php) and
    // blew up with "Call to undefined method Facades\Request::validate()".
    public function test_guest_can_switch_locale(): void
    {
        $this->post(route('locale.set'), ['locale' => 'es'])
            ->assertRedirect();
        $this->assertSame('es', session('locale'));
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->post(route('locale.set'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }
}
