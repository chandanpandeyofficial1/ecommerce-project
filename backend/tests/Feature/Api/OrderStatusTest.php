<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderStatusTest extends ApiTestCase
{
    // Build an order with one item of quantity 2 for a product with stock 8.
    private function order(string $status = 'pending'): Order
    {
        $p = Product::factory()->create(['stock' => 8]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'total' => 10,
            'status' => $status, 'address' => 'x', 'phone' => '1', 'payment_method' => 'cod',
        ]);
        $order->items()->create(['product_id' => $p->id, 'product_name' => $p->name, 'price' => 5, 'quantity' => 2]);

        return $order;
    }

    public function test_forward_flow_writes_history(): void
    {
        $order = $this->order();
        foreach (['confirmed', 'shipped', 'delivered'] as $status) {
            $order->changeStatus($status);
        }

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(['confirmed', 'shipped', 'delivered'], $order->statusHistories->pluck('status')->all());
    }

    public function test_skipping_a_step_is_rejected(): void
    {
        $order = $this->order();
        $this->expectException(ValidationException::class);
        $order->changeStatus('shipped');
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->order()->changeStatus('lost');
    }

    public function test_no_change_out_of_delivered_or_cancelled(): void
    {
        foreach (['delivered', 'cancelled'] as $final) {
            $order = $this->order($final);
            try {
                $order->changeStatus('cancelled');
                $this->fail("Changed out of {$final}");
            } catch (ValidationException) {
                $this->assertSame($final, $order->fresh()->status);
            }
        }
    }

    public function test_cancel_from_shipped_restores_stock(): void
    {
        $order = $this->order('shipped');
        $order->changeStatus('cancelled');

        $this->assertSame(10, $order->items->first()->product->fresh()->stock);
    }
}
