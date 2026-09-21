<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

class CartTest extends ApiTestCase
{
    public function test_add_update_remove(): void
    {
        $this->customer();
        $p = Product::factory()->create(['price' => 10, 'stock' => 5]);

        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 2])->assertCreated();
        // Adding again increases the same line.
        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 1])->assertCreated();

        $res = $this->getJson('/api/cart')->assertOk();
        $res->assertJsonCount(1, 'data.items')->assertJsonPath('data.total', '30.00');

        $id = $res->json('data.items.0.id');
        $this->putJson('/api/cart/'.$id, ['quantity' => 4])->assertOk()->assertJsonPath('data.line_total', '40.00');
        $this->deleteJson('/api/cart/'.$id)->assertOk();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_deleted_product_is_unavailable_in_cart(): void
    {
        $user = $this->customer();
        $p = Product::factory()->create(['stock' => 5]);
        $item = CartItem::create(['user_id' => $user->id, 'product_id' => $p->id, 'quantity' => 1]);
        $p->delete();

        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 1])->assertStatus(422);
        $this->putJson('/api/cart/'.$item->id, ['quantity' => 2])
            ->assertStatus(422)->assertJsonPath('errors.product.0', 'This product is no longer available.');
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.total', '0.00');
    }

    public function test_hidden_product_is_unavailable_in_cart(): void
    {
        $user = $this->customer();
        $p = Product::factory()->create(['stock' => 5, 'is_active' => false]);

        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('errors.product.0', 'This product is no longer available.');
        $this->assertSame(5, $p->fresh()->stock);

        // An item added while active becomes unavailable once hidden.
        $p->update(['is_active' => true]);
        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 1])->assertCreated();
        $item = CartItem::where('user_id', $user->id)->where('product_id', $p->id)->firstOrFail();

        $p->update(['is_active' => false]);
        $this->putJson('/api/cart/'.$item->id, ['quantity' => 2])
            ->assertStatus(422)->assertJsonPath('errors.product.0', 'This product is no longer available.');
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.total', '0.00');
    }

    public function test_quantity_above_stock_is_rejected(): void
    {
        $this->customer();
        $p = Product::factory()->create(['stock' => 2]);

        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_cannot_touch_another_user_item(): void
    {
        $other = User::factory()->create();
        $p = Product::factory()->create(['stock' => 5]);
        $item = CartItem::create(['user_id' => $other->id, 'product_id' => $p->id, 'quantity' => 1]);

        $this->customer();
        $this->putJson('/api/cart/'.$item->id, ['quantity' => 2])->assertStatus(404);
        $this->deleteJson('/api/cart/'.$item->id)->assertStatus(404);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 1]);
    }

    public function test_cart_quantity_over_100_fails(): void
    {
        $p = Product::factory()->create(['stock' => 500]);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/cart', ['product_id' => $p->id, 'quantity' => 101])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
    }
}
