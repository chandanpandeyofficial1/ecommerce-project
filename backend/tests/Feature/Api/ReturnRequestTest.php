<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class ReturnRequestTest extends ApiTestCase
{
    // A delivered order for the given user, with one item of the given quantity.
    private function deliveredOrder(User $user, int $quantity = 2): Order
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::create([
            'user_id' => $user->id, 'total' => 20, 'status' => 'pending',
            'address' => 'x', 'phone' => '9999999999', 'payment_method' => 'cod',
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 10, 'quantity' => $quantity]);
        $order->statusHistories()->create(['status' => 'pending']);
        foreach (['confirmed', 'shipped', 'delivered'] as $status) {
            $order->changeStatus($status);
        }

        return $order->fresh();
    }

    private function payload(Order $order): array
    {
        return [
            'reason' => 'damaged',
            'description' => 'Box arrived crushed.',
            'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        ];
    }

    public function test_happy_path_within_window_creates_items_and_amount(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user, 2);

        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.reason', 'damaged')
            ->assertJsonPath('data.items.0.quantity', 1);

        $this->assertSame(1, $order->returnRequest()->count());
    }

    public function test_refused_if_not_delivered(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::create([
            'user_id' => $user->id, 'total' => 10, 'status' => 'pending',
            'address' => 'x', 'phone' => '9999999999', 'payment_method' => 'cod',
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 10, 'quantity' => 1]);

        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))
            ->assertStatus(422)->assertJsonValidationErrors('order');
    }

    public function test_refused_after_three_day_window(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user);

        $this->travel(4)->days();
        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))
            ->assertStatus(422)->assertJsonValidationErrors('order');
    }

    public function test_allowed_exactly_at_three_days(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user);

        $this->travel(3)->days();
        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))->assertCreated();
    }

    public function test_refused_for_second_request_on_same_order(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user);

        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))->assertCreated();
        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))
            ->assertStatus(422)->assertJsonValidationErrors('order');
    }

    public function test_refused_for_other_users_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->deliveredOrder($owner);
        $this->customer();

        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))->assertNotFound();
    }

    public function test_refused_for_invalid_order_item_id(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user);

        $data = $this->payload($order);
        $data['items'][0]['order_item_id'] = 999999;

        $this->postJson("/api/orders/{$order->id}/returns", $data)
            ->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_refused_for_over_quantity(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user, 2);

        $data = $this->payload($order);
        $data['items'][0]['quantity'] = 3;

        $this->postJson("/api/orders/{$order->id}/returns", $data)
            ->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_validation_errors_are_422_not_500(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user);

        $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'not_a_real_reason',
            'description' => '',
            'items' => [],
        ])->assertStatus(422);
    }

    public function test_index_lists_own_return_requests(): void
    {
        $user = $this->customer();
        $order = $this->deliveredOrder($user);
        $this->postJson("/api/orders/{$order->id}/returns", $this->payload($order))->assertCreated();

        $this->getJson("/api/orders/{$order->id}/returns")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'requested');
    }
}
