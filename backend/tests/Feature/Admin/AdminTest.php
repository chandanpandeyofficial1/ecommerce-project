<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // Order with one item of 3 units; stock is assumed to be reduced already.
    private function orderWithItem(Product $product, string $status = 'pending'): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'total' => 30, 'status' => $status,
            'address' => 'Street 1', 'phone' => '9999999999', 'payment_method' => 'cod',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'price' => 10, 'quantity' => 3,
        ]);
        $order->statusHistories()->create(['status' => $status]);

        return $order;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/products')->assertRedirect(route('login'));
    }

    public function test_customer_cannot_log_into_admin(): void
    {
        User::factory()->create(['email' => 'c@example.com', 'password' => 'secret123']);

        $this->post('/admin/login', ['email' => 'c@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_log_in(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret123', 'role' => 'admin']);

        $this->post('/admin/login', ['email' => 'a@example.com', 'password' => 'secret123'])
            ->assertRedirect('/admin');
        $this->assertAuthenticated();
    }

    public function test_dashboard_loads(): void
    {
        $product = Product::factory()->create(['stock' => 2]);
        $this->orderWithItem($product);

        $this->actingAs($this->admin())->get('/admin')
            ->assertOk()->assertSee($product->name)->assertSee('Latest orders');
    }

    public function test_product_create_with_image_and_image_required(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $data = ['name' => 'Basmati', 'category_id' => $category->id, 'price' => 120, 'stock' => 5, 'unit' => '1 kg'];

        $this->actingAs($this->admin())->post('/admin/products', $data)->assertSessionHasErrors('image');

        $this->post('/admin/products', $data + ['image' => UploadedFile::fake()->image('rice.jpg')])
            ->assertRedirect('/admin/products');

        $product = Product::where('name', 'Basmati')->firstOrFail();
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_product_edit_without_image_keeps_old_and_new_image_replaces_it(): void
    {
        Storage::fake('public');
        $old = UploadedFile::fake()->image('old.jpg')->store('products', 'public');
        $product = Product::factory()->create(['image' => $old]);
        $data = ['name' => 'Renamed', 'category_id' => $product->category_id, 'price' => 10, 'stock' => 5, 'unit' => '1 kg'];

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", $data)->assertRedirect();
        $this->assertSame($old, $product->fresh()->image);
        $this->assertSame('Renamed', $product->fresh()->name);

        $this->put("/admin/products/{$product->id}", $data + ['image' => UploadedFile::fake()->image('new.png')]);
        $new = $product->fresh()->image;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_product_delete_is_soft_and_order_still_shows(): void
    {
        $product = Product::factory()->create();
        $order = $this->orderWithItem($product);

        $this->actingAs($this->admin())->delete("/admin/products/{$product->id}");

        $this->assertSoftDeleted($product);
        $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee($product->name);
    }

    public function test_toggle_active_hides_and_shows_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())->post("/admin/products/{$product->id}/toggle-active")
            ->assertSessionHas('success', "{$product->name} is now hidden from customers.");
        $this->assertFalse($product->fresh()->is_active);

        $this->post("/admin/products/{$product->id}/toggle-active")
            ->assertSessionHas('success', "{$product->name} is now visible to customers.");
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_hidden_product_still_shows_in_admin_list_with_hidden_badge(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())->get('/admin/products')
            ->assertOk()->assertSee($product->name)->assertSee('Hidden');
    }

    public function test_category_delete_blocked_when_it_has_products(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/categories/{$product->category_id}")
            ->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);

        $empty = Category::factory()->create();
        $this->delete("/admin/categories/{$empty->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
    }

    public function test_category_name_must_be_unique(): void
    {
        Category::factory()->create(['name' => 'Rice']);

        $this->actingAs($this->admin())->post('/admin/categories', ['name' => 'Rice'])
            ->assertSessionHasErrors('name');
    }

    public function test_order_status_valid_and_invalid_transition(): void
    {
        $order = $this->orderWithItem(Product::factory()->create());
        $this->actingAs($this->admin());

        $this->post("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])->assertSessionHas('success');
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->post("/admin/orders/{$order->id}/status", ['status' => 'delivered'])->assertSessionHas('error');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_cancel_restores_stock(): void
    {
        $product = Product::factory()->create(['stock' => 7]);
        $order = $this->orderWithItem($product);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled']);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_order_pages_load(): void
    {
        $order = $this->orderWithItem(Product::factory()->create());

        $this->actingAs($this->admin())->get('/admin/orders?status=pending')->assertOk();
        $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee('Street 1');
    }

    public function test_root_redirects_to_admin_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('admin.dashboard'));
    }

    public function test_customer_session_is_sent_to_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect(route('login'));
    }

    public function test_sanctum_token_cannot_open_admin(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;
        $this->withToken($token)->get('/admin')->assertRedirect(route('login'));
    }

    public function test_logout_requires_post(): void
    {
        $this->actingAs($this->admin())->get('/admin/logout')->assertStatus(405);
    }

    public function test_product_list_with_array_search_is_rejected(): void
    {
        $this->actingAs($this->admin())->get('/admin/products?search[]=x')->assertSessionHasErrors('search');
    }

    public function test_order_list_with_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->admin())->get('/admin/orders?status=bogus')->assertSessionHasErrors('status');
    }

    public function test_category_count_includes_soft_deleted_products(): void
    {
        $category = Category::factory()->create(['name' => 'Fruit']);
        Product::factory()->create(['category_id' => $category->id])->delete();

        $this->actingAs($this->admin())->get('/admin/categories')
            ->assertViewHas('categories', fn ($c) => $c->first()->products_count === 1);
    }

    public function test_dashboard_chart_has_seven_days(): void
    {
        $this->orderWithItem(Product::factory()->create());

        $this->actingAs($this->admin())->get('/admin/')
            ->assertOk()
            ->assertViewHas('chart', fn ($c) => count($c['labels']) === 7 && array_sum($c['values']) === 1);
    }

    public function test_dashboard_revenue_counts_only_delivered_orders(): void
    {
        $product = Product::factory()->create();
        $this->orderWithItem($product, 'delivered');
        $this->orderWithItem($product, 'pending');

        $this->actingAs($this->admin())->get('/admin/')
            ->assertOk()
            ->assertViewHas('revenue', fn ($r) => (float) $r === 30.0);
    }

    public function test_orders_list_status_filter_and_counts(): void
    {
        $product = Product::factory()->create();
        $this->orderWithItem($product, 'pending');
        $shipped = $this->orderWithItem($product, 'shipped');

        $this->actingAs($this->admin())->get('/admin/orders?status=shipped')
            ->assertOk()
            ->assertViewHas('orders', fn ($o) => $o->count() === 1 && $o->first()->id === $shipped->id)
            ->assertViewHas('counts', fn ($c) => $c['pending'] === 1 && $c['shipped'] === 1);
    }

    public function test_product_price_zero_fails(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->admin())->post('/admin/products', ['name' => 'Basmati', 'category_id' => $category->id, 'price' => 0, 'stock' => 5, 'unit' => '1 kg'])
            ->assertSessionHasErrors('price');
    }

    public function test_category_name_one_char_fails(): void
    {
        $this->actingAs($this->admin())->post('/admin/categories', ['name' => 'R'])
            ->assertSessionHasErrors('name');
    }
}
