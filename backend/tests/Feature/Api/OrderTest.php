<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class OrderTest extends ApiTestCase
{
    private array $shipping = ['address' => '12 Main Road', 'phone' => '9999999999'];

    // Make an order row directly for a user.
    private function makeOrder(int $userId): Order
    {
        return Order::create([
            'user_id' => $userId, 'total' => 5, 'status' => 'pending',
            'address' => 'x', 'phone' => '1', 'payment_method' => 'cod',
        ]);
    }

    public function test_order_from_cart_reduces_stock_clears_cart_and_snapshots_price(): void
    {
        $user = $this->customer();
        $a = Product::factory()->create(['price' => 10, 'stock' => 5]);
        $b = Product::factory()->create(['price' => 20, 'stock' => 5]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $a->id, 'quantity' => 2]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $b->id, 'quantity' => 1]);

        $res = $this->postJson('/api/orders', $this->shipping + ['use_cart' => true])
            ->assertCreated()
            ->assertJsonPath('data.total', '40.00')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(3, $a->fresh()->stock);
        $this->assertSame(4, $b->fresh()->stock);
        $this->assertSame(0, $user->cartItems()->count());

        // A later price change must not affect the order.
        $a->update(['price' => 99]);
        $this->getJson('/api/orders/'.$res->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.items.0.price', '10.00')
            ->assertJsonPath('data.status_history.0.status', 'pending');
    }

    public function test_buy_now_leaves_cart_alone(): void
    {
        $user = $this->customer();
        $a = Product::factory()->create(['price' => 10, 'stock' => 5]);
        $b = Product::factory()->create(['stock' => 5]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $b->id, 'quantity' => 1]);

        $this->postJson('/api/orders', $this->shipping + ['product_id' => $a->id, 'quantity' => 3])
            ->assertCreated()->assertJsonPath('data.total', '30.00');

        $this->assertSame(2, $a->fresh()->stock);
        $this->assertSame(1, $user->cartItems()->count());
    }

    public function test_insufficient_stock_rejects_and_changes_nothing(): void
    {
        $user = $this->customer();
        $a = Product::factory()->create(['stock' => 5]);
        $b = Product::factory()->create(['stock' => 1]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $a->id, 'quantity' => 2]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $b->id, 'quantity' => 1]);
        // Stock drops after the item was added to the cart.
        $b->update(['stock' => 0]);

        $this->postJson('/api/orders', $this->shipping + ['use_cart' => true])
            ->assertStatus(422)->assertJsonValidationErrors('stock');

        $this->assertSame(5, $a->fresh()->stock);
        $this->assertSame(2, $user->cartItems()->count());
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cancel_restores_stock_and_only_when_pending(): void
    {
        $this->customer();
        $p = Product::factory()->create(['stock' => 5]);
        $id = $this->postJson('/api/orders', $this->shipping + ['product_id' => $p->id, 'quantity' => 2])
            ->json('data.id');
        $this->assertSame(3, $p->fresh()->stock);

        $this->postJson("/api/orders/$id/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(5, $p->fresh()->stock);

        // Cancelling twice is refused and does not add stock again.
        $this->postJson("/api/orders/$id/cancel")->assertStatus(422);
        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_use_cart_with_empty_cart_is_rejected(): void
    {
        $this->customer();
        $this->postJson('/api/orders', $this->shipping + ['use_cart' => true])->assertStatus(422);
    }

    public function test_use_cart_false_needs_product(): void
    {
        $this->customer();
        foreach ([false, 0, '0'] as $v) {
            $this->postJson('/api/orders', $this->shipping + ['use_cart' => $v])
                ->assertStatus(422)->assertJsonValidationErrors(['product_id', 'quantity']);
        }
    }

    public function test_use_cart_with_product_is_rejected(): void
    {
        $this->customer();
        $p = Product::factory()->create(['stock' => 5]);
        $this->postJson('/api/orders', $this->shipping + ['use_cart' => true, 'product_id' => $p->id, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_cancel_restores_stock_of_deleted_product(): void
    {
        $this->customer();
        $p = Product::factory()->create(['stock' => 5]);
        $id = $this->postJson('/api/orders', $this->shipping + ['product_id' => $p->id, 'quantity' => 2])
            ->json('data.id');
        $p->delete();

        $this->postJson("/api/orders/$id/cancel")->assertOk();
        $this->assertSame(5, Product::withTrashed()->find($p->id)->stock);
    }

    public function test_order_with_deleted_product_in_cart_is_refused(): void
    {
        $user = $this->customer();
        $p = Product::factory()->create(['name' => 'Old Lamp', 'stock' => 5]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $p->id, 'quantity' => 1]);
        $p->delete();

        $this->postJson('/api/orders', $this->shipping + ['use_cart' => true])
            ->assertStatus(422)->assertJsonPath('errors.product.0', 'Old Lamp is no longer available.');
    }

    public function test_order_with_hidden_product_in_cart_is_refused(): void
    {
        $user = $this->customer();
        $p = Product::factory()->create(['name' => 'Old Lamp', 'stock' => 5, 'is_active' => false]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $p->id, 'quantity' => 1]);

        $this->postJson('/api/orders', $this->shipping + ['use_cart' => true])
            ->assertStatus(422)->assertJsonPath('errors.product.0', 'Old Lamp is no longer available.');

        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_buy_now_with_hidden_product_is_refused(): void
    {
        $this->customer();
        $p = Product::factory()->create(['name' => 'Old Lamp', 'stock' => 5, 'is_active' => false]);

        $this->postJson('/api/orders', $this->shipping + ['product_id' => $p->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('errors.product.0', 'Old Lamp is no longer available.');

        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_order_history_still_shows_a_now_hidden_product(): void
    {
        $this->customer();
        $p = Product::factory()->create(['name' => 'Old Lamp', 'price' => 15, 'stock' => 5]);
        $id = $this->postJson('/api/orders', $this->shipping + ['product_id' => $p->id, 'quantity' => 2])
            ->json('data.id');

        $p->update(['is_active' => false]);

        $this->getJson('/api/orders/'.$id)->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Old Lamp')
            ->assertJsonPath('data.items.0.price', '15.00');
    }

    public function test_cancel_refused_after_confirmed(): void
    {
        $this->customer();
        $p = Product::factory()->create(['stock' => 5]);
        $id = $this->postJson('/api/orders', $this->shipping + ['product_id' => $p->id, 'quantity' => 1])
            ->json('data.id');
        Order::find($id)->changeStatus('confirmed');

        $this->postJson("/api/orders/$id/cancel")->assertStatus(422);
        $this->assertSame(4, $p->fresh()->stock);
    }

    public function test_orders_are_private_and_listed_newest_first(): void
    {
        $theirs = $this->makeOrder(User::factory()->create()->id);

        $user = $this->customer();
        $this->makeOrder($user->id);
        $second = $this->makeOrder($user->id);

        $this->getJson('/api/orders')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/orders/'.$theirs->id)->assertStatus(404);
        $this->postJson("/api/orders/{$theirs->id}/cancel")->assertStatus(404);
    }

    public function test_order_bad_phone_fails(): void
    {
        $p = Product::factory()->create(['stock' => 5]);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/orders', ['phone' => 'abc'] + $this->shipping + ['product_id' => $p->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_order_short_address_fails(): void
    {
        $p = Product::factory()->create(['stock' => 5]);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/orders', ['address' => 'abc'] + $this->shipping + ['product_id' => $p->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('address');
    }

    public function test_buy_now_quantity_over_100_fails(): void
    {
        $p = Product::factory()->create(['stock' => 500]);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/orders', $this->shipping + ['product_id' => $p->id, 'quantity' => 101])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
    }
}
