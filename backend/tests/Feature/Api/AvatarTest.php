<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class AvatarTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // Upload a valid image, see it in the response and in /api/me.
    public function test_upload_stores_file_and_shows_in_me(): void
    {
        $user = $this->customer();

        $res = $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()
            ->assertJsonPath('message', 'Profile photo updated.')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'role', 'avatar_url']]);

        $path = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertSame(asset('storage/'.$path), $res->json('data.avatar_url'));

        $this->getJson('/api/me')->assertJsonPath('data.avatar_url', asset('storage/'.$path));
    }

    // A second upload removes the first file.
    public function test_second_upload_deletes_previous_file(): void
    {
        $user = $this->customer();

        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $first = $user->fresh()->avatar;

        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('b.png')])->assertOk();
        $second = $user->fresh()->avatar;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    // Delete removes the file and clears the url.
    public function test_delete_removes_file(): void
    {
        $user = $this->customer();
        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $path = $user->fresh()->avatar;

        $this->deleteJson('/api/profile/avatar')
            ->assertOk()
            ->assertJsonPath('message', 'Profile photo removed.')
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar);

        // Deleting again with no avatar is fine.
        $this->deleteJson('/api/profile/avatar')->assertOk();
    }

    // Wrong type, fake extension, too big and missing file are all rejected.
    public function test_invalid_files_are_rejected(): void
    {
        $this->customer();

        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.gif')])
            ->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->postJson('/api/profile/avatar', ['avatar' => $this->textFileNamedJpg()])
            ->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('big.jpg')->size(2049)])
            ->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->postJson('/api/profile/avatar', [])
            ->assertStatus(422)->assertJsonValidationErrors('avatar');
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])->assertStatus(401);
        $this->deleteJson('/api/profile/avatar')->assertStatus(401);
    }

    // The avatar routes are customer only.
    public function test_admin_is_forbidden(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        Sanctum::actingAs($admin);

        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])->assertStatus(403);
        $this->deleteJson('/api/profile/avatar')->assertStatus(403);
    }

    // Register and login keep their shape and add avatar_url.
    public function test_register_and_login_include_avatar_url(): void
    {
        $keys = ['id', 'name', 'email', 'phone', 'role', 'avatar_url'];

        $this->postJson('/api/register', [
            'name' => 'Sam', 'email' => 'sam@example.com',
            'password' => 'secret12', 'password_confirmation' => 'secret12',
        ])->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['user' => $keys, 'token']])
            ->assertJsonPath('data.user.avatar_url', null);

        $this->postJson('/api/login', ['email' => 'sam@example.com', 'password' => 'secret12'])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => ['user' => $keys, 'token']])
            ->assertJsonMissingPath('data.user.password');
    }

    // A real text file that only pretends to be a jpg.
    private function textFileNamedJpg(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'av');
        file_put_contents($path, 'just some text, not an image');

        return new UploadedFile($path, 'a.jpg', null, null, true);
    }
}
