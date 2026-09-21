<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    // A card order with the given payment status.
    private function cardOrder(string $paymentStatus): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id, 'total' => 20, 'status' => 'pending', 'address' => 'x',
            'phone' => '9999999999', 'payment_method' => 'card', 'payment_status' => $paymentStatus,
        ]);
    }

    public function test_admin_cannot_ship_an_unpaid_card_order_but_can_cancel_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->cardOrder('unpaid');

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_paid_card_order_can_be_confirmed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->cardOrder('paid');

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertSessionHas('success');
    }

    public function test_pages_show_payment_badges_and_refund_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->cardOrder('paid');
        $order->update(['status' => 'cancelled']);

        $this->actingAs($admin)->get('/admin/orders')->assertOk()->assertSee('Paid');
        $this->actingAs($admin)->get("/admin/orders/{$order->id}")
            ->assertOk()->assertSee('Refund this payment in the Razorpay dashboard.');
    }
}
