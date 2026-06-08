<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $u = User::forceCreate([
            'name' => 'Av Test', 'email' => 'avtest-'.uniqid().'@t.l',
            'password' => bcrypt('x'),
            'cp_id' => null, 'membership_status' => 'approved',
        ]);
        $u->forceFill(['role_id' => $role->id])->save();
        return $u;
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $u = $this->makeUser();

        $response = $this->actingAs($u)->patch(route('profile.update'), [
            'name' => $u->name,
            'email' => $u->email,
            'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
        ]);
        $response->assertRedirect(route('profile.edit'));

        $u->refresh();
        $this->assertNotNull($u->avatar_path);
        $this->assertStringStartsWith("avatars/{$u->id}/", $u->avatar_path);
        Storage::disk('public')->assertExists($u->avatar_path);
        $this->assertNotNull($u->avatar_url);
    }

    public function test_oversize_image_is_rejected(): void
    {
        Storage::fake('public');
        $u = $this->makeUser();

        $response = $this->actingAs($u)->patch(route('profile.update'), [
            'name' => $u->name,
            'email' => $u->email,
            // image() size param is in KB; 4000 KB > 3MB limit.
            'avatar' => UploadedFile::fake()->image('big.png')->size(4000),
        ]);
        $response->assertSessionHasErrors('avatar');
        $u->refresh();
        $this->assertNull($u->avatar_path);
    }

    public function test_non_image_is_rejected(): void
    {
        Storage::fake('public');
        $u = $this->makeUser();

        $response = $this->actingAs($u)->patch(route('profile.update'), [
            'name' => $u->name,
            'email' => $u->email,
            'avatar' => UploadedFile::fake()->create('readme.txt', 100, 'text/plain'),
        ]);
        $response->assertSessionHasErrors('avatar');
    }

    public function test_uploading_replaces_previous_avatar(): void
    {
        Storage::fake('public');
        $u = $this->makeUser();

        // First upload
        $this->actingAs($u)->patch(route('profile.update'), [
            'name' => $u->name, 'email' => $u->email,
            'avatar' => UploadedFile::fake()->image('first.png', 256, 256),
        ]);
        $first = $u->fresh()->avatar_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        // Second upload — should remove the first file from storage.
        $this->actingAs($u)->patch(route('profile.update'), [
            'name' => $u->name, 'email' => $u->email,
            'avatar' => UploadedFile::fake()->image('second.png', 256, 256),
        ]);
        $second = $u->fresh()->avatar_path;
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_avatar_url_appears_in_user_serialization(): void
    {
        Storage::fake('public');
        $u = $this->makeUser();

        $this->assertNull($u->avatar_url);
        $u->forceFill(['avatar_path' => "avatars/{$u->id}/sample.jpg"])->save();
        $this->assertNotNull($u->avatar_url);
        $this->assertStringContainsString('avatars/'.$u->id.'/sample.jpg', $u->avatar_url);

        $arr = $u->fresh()->toArray();
        $this->assertArrayHasKey('avatar_url', $arr);
    }
}
