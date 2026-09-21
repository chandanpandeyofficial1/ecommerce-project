<?php

namespace Tests\Feature\Admin;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_users_list_loads_and_shows_seeded_customers(): void
    {
        $customer = User::factory()->create(['name' => 'Riya Sharma', 'email' => 'riya@example.com']);

        $this->actingAs($this->admin())->get('/admin/users')
            ->assertOk()->assertSee('Riya Sharma')->assertSee('riya@example.com');
    }

    public function test_search_by_name_filters_correctly(): void
    {
        User::factory()->create(['name' => 'Amit Kumar', 'email' => 'amit@example.com']);
        User::factory()->create(['name' => 'Sunita Rao', 'email' => 'sunita@example.com']);

        $this->actingAs($this->admin())->get('/admin/users?search=Amit')
            ->assertOk()->assertSee('Amit Kumar')->assertDontSee('Sunita Rao');
    }

    public function test_search_by_email_filters_correctly(): void
    {
        User::factory()->create(['name' => 'Amit Kumar', 'email' => 'amit@example.com']);
        User::factory()->create(['name' => 'Sunita Rao', 'email' => 'sunita@example.com']);

        $this->actingAs($this->admin())->get('/admin/users?search=sunita@example.com')
            ->assertOk()->assertSee('Sunita Rao')->assertDontSee('Amit Kumar');
    }

    public function test_search_wildcards_are_escaped(): void
    {
        User::factory()->create(['name' => 'Wild_Card', 'email' => 'wild@example.com']);
        User::factory()->create(['name' => 'WildXCard', 'email' => 'wildx@example.com']);

        // The literal underscore should not act as a single-character wildcard.
        $this->actingAs($this->admin())->get('/admin/users?'.http_build_query(['search' => 'Wild_Card']))
            ->assertOk()->assertSee('Wild_Card')->assertDontSee('WildXCard');
    }

    public function test_logout_everywhere_deletes_tokens_and_revokes_api_access(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->actingAs($this->admin())->post("/admin/users/{$customer->id}/logout-everywhere")
            ->assertRedirect()
            ->assertSessionHas('success', "{$customer->name} has been logged out on every device.");

        $this->assertSame(0, $customer->tokens()->count());

        // Drop the admin's session guard so the sanctum guard can't fall back to it.
        // Drop the admin's session guard so it doesn't leak into the next request.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_everywhere_succeeds_when_user_already_has_no_tokens(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->admin())->post("/admin/users/{$customer->id}/logout-everywhere")
            ->assertSessionHas('success', "{$customer->name} has been logged out on every device.");
    }

    public function test_logout_everywhere_is_blocked_for_admin_target(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $token = $otherAdmin->createToken('device')->plainTextToken;

        $this->actingAs($this->admin())->post("/admin/users/{$otherAdmin->id}/logout-everywhere")
            ->assertSessionHas('error');

        $this->assertSame(1, $otherAdmin->tokens()->count());
        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_disable_revokes_tokens_blocks_login_and_re_enable_restores_access(): void
    {
        $customer = User::factory()->create(['password' => 'secret12']);
        $token = $customer->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->actingAs($this->admin())->post("/admin/users/{$customer->id}/toggle-active")
            ->assertRedirect()
            ->assertSessionHas('success', "{$customer->name} has been disabled and logged out.");

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame(0, $customer->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $customer->email, 'password' => 'secret12'])
            ->assertStatus(403)->assertJson(['message' => 'Your account has been disabled. Contact support.']);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->actingAs($this->admin())->post("/admin/users/{$customer->id}/toggle-active")
            ->assertSessionHas('success', "{$customer->name} has been enabled.");

        $this->assertTrue($customer->fresh()->is_active);
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->postJson('/api/login', ['email' => $customer->email, 'password' => 'secret12'])->assertOk();
    }

    public function test_toggle_active_is_blocked_for_admin_target(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($this->admin())->post("/admin/users/{$otherAdmin->id}/toggle-active")
            ->assertSessionHas('error');

        $this->assertTrue($otherAdmin->fresh()->is_active);
    }

    public function test_delete_removes_customer_without_orders_and_their_cart_items(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($this->admin())->delete("/admin/users/{$customer->id}")
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', "{$customer->name} has been deleted.");

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseMissing('cart_items', ['user_id' => $customer->id]);
    }

    public function test_delete_is_refused_when_customer_has_orders(): void
    {
        $customer = User::factory()->create();
        Order::create([
            'user_id' => $customer->id, 'total' => 5, 'status' => 'pending',
            'address' => 'x', 'phone' => '1', 'payment_method' => 'cod',
        ]);

        $this->actingAs($this->admin())->delete("/admin/users/{$customer->id}")
            ->assertSessionHas('error', 'Cannot delete a customer with existing orders. Disable the account instead.');

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_delete_is_blocked_for_admin_target(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($this->admin())->delete("/admin/users/{$otherAdmin->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_guest_is_blocked_from_users_pages(): void
    {
        $customer = User::factory()->create();

        $this->get('/admin/users')->assertRedirect(route('login'));
        $this->post("/admin/users/{$customer->id}/logout-everywhere")->assertRedirect(route('login'));
    }

    public function test_customer_session_is_blocked_from_users_pages(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())->get('/admin/users')->assertRedirect(route('login'));
        $this->post("/admin/users/{$target->id}/logout-everywhere")->assertRedirect(route('login'));
    }
}
