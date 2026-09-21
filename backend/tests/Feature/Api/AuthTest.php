<?php

namespace Tests\Feature\Api;

use App\Models\User;

class AuthTest extends ApiTestCase
{
    // Register always makes a customer, even if role is sent.
    public function test_register_creates_customer_and_ignores_role(): void
    {
        $res = $this->postJson('/api/register', [
            'name' => 'Sam', 'email' => 'sam@example.com',
            'password' => 'secret12', 'password_confirmation' => 'secret12',
            'role' => 'admin',
        ]);

        $res->assertCreated()->assertJsonStructure(['data' => ['user', 'token']]);
        $this->assertSame('customer', User::where('email', 'sam@example.com')->value('role'));
    }

    public function test_login_success_and_failure(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret12']);

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'secret12'])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertStatus(401)->assertJson(['message' => 'Invalid email or password.']);
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'd@example.com', 'password' => 'secret12', 'is_active' => false]);

        $this->postJson('/api/login', ['email' => 'd@example.com', 'password' => 'secret12'])
            ->assertStatus(403)->assertJson(['message' => 'Your account has been disabled. Contact support.']);
    }

    public function test_unauthenticated_is_blocked_and_logout_works(): void
    {
        $this->getJson('/api/products')->assertStatus(401);

        $user = User::factory()->create(['password' => 'secret12']);
        $token = $user->createToken('t')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        // Only the token used for logout is gone.
        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/me')->assertOk();
    }

    public function test_admin_cannot_place_orders(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/orders', ['address' => 'x', 'phone' => '1', 'use_cart' => true])->assertStatus(403);
    }

    public function test_admin_cannot_use_customer_endpoints(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/cart')->assertStatus(403);
        $this->getJson('/api/categories')->assertOk();
    }

    private function regData(array $over = []): array
    {
        return $over + ['name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'secret12', 'password_confirmation' => 'secret12'];
    }

    public function test_register_short_password_fails(): void
    {
        $this->postJson('/api/register', $this->regData(['password' => 'abcdefg', 'password_confirmation' => 'abcdefg']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_register_bad_phone_fails(): void
    {
        $this->postJson('/api/register', $this->regData(['phone' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_register_one_char_name_fails(): void
    {
        $this->postJson('/api/register', $this->regData(['name' => 'S']))
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }
}
