<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    // A delivered order with two items of quantity 2 each (price 10 and 15), and a return covering
    // one full line and half of the other, so the refund amount is a partial sum.
    private function deliveredOrderWithReturn(array $orderExtra = []): array
    {
        $user = User::factory()->create();
        $order = Order::create($orderExtra + [
            'user_id' => $user->id, 'total' => 50, 'status' => 'delivered',
            'address' => 'x', 'phone' => '9999999999', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
        ]);
        $itemA = $order->items()->create(['product_id' => Product::factory()->create()->id, 'product_name' => 'A', 'price' => 10, 'quantity' => 2]);
        $itemB = $order->items()->create(['product_id' => Product::factory()->create()->id, 'product_name' => 'B', 'price' => 15, 'quantity' => 2]);
        $order->statusHistories()->create(['status' => 'delivered']);

        $return = $order->returnRequest()->create([
            'user_id' => $user->id, 'reason' => 'damaged', 'description' => 'broken',
        ]);
        $return->items()->create(['order_item_id' => $itemA->id, 'quantity' => 2]); // full line: 20
        $return->items()->create(['order_item_id' => $itemB->id, 'quantity' => 1]); // partial: 15

        return [$order, $return];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_approve_computes_correct_partial_refund_amount(): void
    {
        $admin = $this->admin();
        [$order, $return] = $this->deliveredOrderWithReturn();

        $this->actingAs($admin)->post(route('admin.returns.approve', $return))->assertSessionHas('success');

        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertSame('35.00', (string) $return->refund_amount);
    }

    public function test_reject_requires_reason_and_halts_flow(): void
    {
        $admin = $this->admin();
        [, $return] = $this->deliveredOrderWithReturn();

        $this->actingAs($admin)->post(route('admin.returns.reject', $return), [])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame('requested', $return->fresh()->status);

        $this->actingAs($admin)->post(route('admin.returns.reject', $return), ['rejection_reason' => 'Used product'])
            ->assertSessionHas('success');
        $this->assertSame('rejected', $return->fresh()->status);

        // Cannot approve after rejecting.
        $this->actingAs($admin)->post(route('admin.returns.approve', $return))->assertSessionHas('error');
    }

    public function test_mark_returned_only_after_approved(): void
    {
        $admin = $this->admin();
        [, $return] = $this->deliveredOrderWithReturn();

        $this->actingAs($admin)->post(route('admin.returns.mark-returned', $return))->assertSessionHas('error');
        $this->assertSame('requested', $return->fresh()->status);

        $this->actingAs($admin)->post(route('admin.returns.approve', $return));
        $this->actingAs($admin)->post(route('admin.returns.mark-returned', $return))->assertSessionHas('success');
        $this->assertSame('returned', $return->fresh()->status);
    }

    public function test_manual_refund_works_for_cod_order(): void
    {
        $admin = $this->admin();
        [, $return] = $this->deliveredOrderWithReturn();
        $this->actingAs($admin)->post(route('admin.returns.approve', $return));
        $this->actingAs($admin)->post(route('admin.returns.mark-returned', $return));

        $this->actingAs($admin)->post(route('admin.returns.refund', $return), ['refund_reference' => 'CASH-123'])
            ->assertSessionHas('success');

        $return->refresh();
        $this->assertSame('refunded', $return->status);
        $this->assertSame('manual', $return->refund_method);
        $this->assertSame('CASH-123', $return->refund_reference);
    }

    public function test_razorpay_refund_succeeds_with_http_fake(): void
    {
        config(['services.razorpay.key_id' => 'rzp_test_id', 'services.razorpay.key_secret' => 'secret']);
        Http::fake(['api.razorpay.com/v1/payments/*/refund' => Http::response(['id' => 'rfnd_1', 'status' => 'processed'])]);

        $admin = $this->admin();
        [$order, $return] = $this->deliveredOrderWithReturn([
            'payment_method' => 'card', 'payment_status' => 'paid', 'razorpay_payment_id' => 'pay_123',
        ]);
        $this->actingAs($admin)->post(route('admin.returns.approve', $return));
        $this->actingAs($admin)->post(route('admin.returns.mark-returned', $return));

        $this->actingAs($admin)->post(route('admin.returns.refund', $return))->assertSessionHas('success');

        $return->refresh();
        $this->assertSame('refunded', $return->status);
        $this->assertSame('razorpay', $return->refund_method);
        $this->assertSame('rfnd_1', $return->refund_reference);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/payments/pay_123/refund') && $r['amount'] === 3500);
    }

    public function test_razorpay_refund_failure_leaves_status_unchanged(): void
    {
        config(['services.razorpay.key_id' => 'rzp_test_id', 'services.razorpay.key_secret' => 'secret']);
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => 'bad'], 500)]);

        $admin = $this->admin();
        [, $return] = $this->deliveredOrderWithReturn([
            'payment_method' => 'card', 'payment_status' => 'paid', 'razorpay_payment_id' => 'pay_123',
        ]);
        $this->actingAs($admin)->post(route('admin.returns.approve', $return));
        $this->actingAs($admin)->post(route('admin.returns.mark-returned', $return));

        $this->actingAs($admin)->post(route('admin.returns.refund', $return))->assertSessionHas('error');

        $this->assertSame('returned', $return->fresh()->status);
        $this->assertNull($return->fresh()->refund_reference);
    }

    public function test_admin_actions_require_admin_session(): void
    {
        [, $return] = $this->deliveredOrderWithReturn();
        $customer = User::factory()->create();

        // Guest is redirected to login.
        $this->post(route('admin.returns.approve', $return))->assertRedirect(route('login'));

        // A logged in customer is bounced back to login too.
        $this->actingAs($customer)->post(route('admin.returns.approve', $return))->assertRedirect(route('login'));

        $this->assertSame('requested', $return->fresh()->status);
    }
}
