<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

class PaymentTest extends ApiTestCase
{
    private array $shipping = ['address' => '12 Main Road', 'phone' => '9999999999'];

    // Same secrets are used for signing in the tests below.
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.razorpay.key_id' => 'rzp_test_id',
            'services.razorpay.key_secret' => 'test_key_secret',
            'services.razorpay.webhook_secret' => 'test_webhook_secret',
        ]);
    }

    // Fake the Razorpay create-link call; the link id can be changed per test.
    private function fakeCreate(string $id = 'plink_1'): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['id' => $id, 'short_url' => "https://rzp.io/i/{$id}"], 200)]);
    }

    // A card order with reserved stock, as the store endpoint leaves it.
    private function cardOrder(User $user, Product $product, array $extra = []): Order
    {
        $order = Order::create($extra + [
            'user_id' => $user->id, 'total' => 20, 'status' => 'pending', 'address' => 'x', 'phone' => '9999999999',
            'payment_method' => 'card', 'payment_status' => 'unpaid', 'payment_reference' => 'plink_1',
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 10, 'quantity' => 2]);
        $order->statusHistories()->create(['status' => 'pending']);

        return $order;
    }

    // Build a signed webhook call.
    private function webhook(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $body, 'test_webhook_secret');

        return $this->call('POST', '/api/razorpay/webhook', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    // Body of a payment link event.
    private function event(string $event, string $linkId = 'plink_1'): array
    {
        return ['event' => $event, 'payload' => ['payment_link' => ['entity' => ['id' => $linkId]]]];
    }

    // Query string Razorpay adds to the callback, signed with the key secret.
    private function callbackQuery(string $status = 'paid', ?string $signature = null): array
    {
        $q = [
            'razorpay_payment_id' => 'pay_1', 'razorpay_payment_link_id' => 'plink_1',
            'razorpay_payment_link_reference_id' => 'ord1-1', 'razorpay_payment_link_status' => $status,
        ];
        $q['razorpay_signature'] = $signature ?? hash_hmac('sha256', "plink_1|ord1-1|{$status}|pay_1", 'test_key_secret');

        return $q;
    }

    public function test_card_order_returns_checkout_url_and_reserves_stock(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create(['price' => 10.50, 'stock' => 5]);
        $this->fakeCreate();

        $this->postJson('/api/orders', $this->shipping + ['product_id' => $product->id, 'quantity' => 2, 'payment_method' => 'card'])
            ->assertCreated()
            ->assertJsonPath('checkout_url', 'https://rzp.io/i/plink_1')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonMissingPath('data.payment_reference');

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('plink_1', $user->orders()->first()->payment_reference);

        // Amount is integer paise, expiry is well past Razorpay's 15 minute minimum.
        Http::assertSent(function (HttpRequest $r) {
            return $r['amount'] === 2100 && $r['currency'] === 'INR' && $r['accept_partial'] === false
                && $r['expire_by'] >= time() + 15 * 60 && strlen($r['reference_id']) <= 40
                && str_ends_with($r['callback_url'], '/payment/callback') && $r['callback_method'] === 'get';
        });
    }

    public function test_cod_order_is_unchanged_and_has_no_checkout_url(): void
    {
        $this->customer();
        $product = Product::factory()->create(['price' => 10, 'stock' => 5]);
        Http::fake();

        $this->postJson('/api/orders', $this->shipping + ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('checkout_url', null)
            ->assertJsonPath('data.payment_method', 'cod');

        Http::assertNothingSent();
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_gateway_failure_cancels_order_and_restores_stock(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create(['price' => 10, 'stock' => 5]);
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => 'bad'], 500)]);

        $this->postJson('/api/orders', $this->shipping + ['product_id' => $product->id, 'quantity' => 2, 'payment_method' => 'card'])
            ->assertStatus(502)->assertJsonStructure(['message']);

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('cancelled', $user->orders()->first()->status);
    }

    public function test_pay_creates_a_new_link_with_a_new_reference(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->cardOrder($user, $product);
        $this->fakeCreate('plink_2');

        $this->travel(2)->seconds();
        $this->postJson("/api/orders/{$order->id}/pay")
            ->assertOk()->assertJsonPath('checkout_url', 'https://rzp.io/i/plink_2');

        $this->assertSame('plink_2', $order->fresh()->payment_reference);
        Http::assertSent(fn (HttpRequest $r) => $r['reference_id'] !== "ord{$order->id}-".(time() - 2) && str_starts_with($r['reference_id'], "ord{$order->id}-"));
    }

    public function test_pay_is_refused_for_cod_paid_cancelled_and_other_users(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create(['stock' => 3]);
        Http::fake();

        $cod = $this->cardOrder($user, $product, ['payment_method' => 'cod']);
        $paid = $this->cardOrder($user, $product, ['payment_status' => 'paid']);
        $cancelled = $this->cardOrder($user, $product, ['status' => 'cancelled']);
        $other = $this->cardOrder(User::factory()->create(), $product);

        foreach ([$cod, $paid, $cancelled] as $order) {
            $this->postJson("/api/orders/{$order->id}/pay")->assertStatus(422)->assertJsonValidationErrors('payment');
        }
        $this->postJson("/api/orders/{$other->id}/pay")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_callback_with_valid_signature_marks_paid_once(): void
    {
        $user = User::factory()->create();
        $order = $this->cardOrder($user, Product::factory()->create());

        $this->get('/payment/callback?'.http_build_query($this->callbackQuery()))
            ->assertOk()
            ->assertSee('Payment received')
            // The page hands the customer back to the mobile app.
            ->assertSee('minigrocery://payment/result?status=paid&order='.$order->id);
        $paidAt = $order->fresh()->paid_at;
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($paidAt);

        $this->travel(5)->minutes();
        $this->get('/payment/callback?'.http_build_query($this->callbackQuery()))->assertOk();
        $this->assertEquals($paidAt, $order->fresh()->paid_at);
    }

    public function test_callback_with_bad_or_tampered_signature_changes_nothing(): void
    {
        $order = $this->cardOrder(User::factory()->create(), Product::factory()->create());

        $this->get('/payment/callback?'.http_build_query($this->callbackQuery('paid', 'bad')))
            ->assertSee('Payment not completed')
            ->assertSee('minigrocery://payment/result?status=failed')
            ->assertDontSee('order=');
        // Signed for "created" but the status was changed to "paid" in the url.
        $tampered = $this->callbackQuery('created');
        $tampered['razorpay_payment_link_status'] = 'paid';
        $this->get('/payment/callback?'.http_build_query($tampered))->assertSee('Payment not completed');
        // Valid signature but not paid.
        $this->get('/payment/callback?'.http_build_query($this->callbackQuery('expired')))->assertSee('Payment not completed');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_show_marks_paid_when_gateway_says_paid(): void
    {
        $user = $this->customer();
        $order = $this->cardOrder($user, Product::factory()->create());
        Http::fake(['api.razorpay.com/v1/payment_links/plink_1' => Http::response(['status' => 'paid'])]);

        $this->getJson("/api/orders/{$order->id}")->assertOk()->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_show_cancels_expired_order_and_restores_stock_once(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->cardOrder($user, $product);
        Http::fake(['api.razorpay.com/v1/payment_links/plink_1' => Http::response(['status' => 'expired'])]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.payment_status', 'failed');
        $this->getJson("/api/orders/{$order->id}")->assertOk();

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_show_ignores_gateway_errors(): void
    {
        $user = $this->customer();
        $order = $this->cardOrder($user, Product::factory()->create());
        Http::fake(['api.razorpay.com/*' => Http::response('down', 500)]);

        $this->getJson("/api/orders/{$order->id}")->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_webhook_paid_is_idempotent(): void
    {
        $order = $this->cardOrder(User::factory()->create(), Product::factory()->create());

        $this->webhook($this->event('payment_link.paid'))->assertOk();
        $paidAt = $order->fresh()->paid_at;
        $this->travel(5)->minutes();
        $this->webhook($this->event('payment_link.paid'))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertEquals($paidAt, $order->fresh()->paid_at);
    }

    public function test_webhook_with_invalid_signature_or_no_secret_is_rejected(): void
    {
        $order = $this->cardOrder(User::factory()->create(), Product::factory()->create());

        $this->webhook($this->event('payment_link.paid'), 'wrong')->assertStatus(400);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        config(['services.razorpay.webhook_secret' => '']);
        $this->webhook($this->event('payment_link.paid'), hash_hmac('sha256', json_encode($this->event('payment_link.paid')), ''))->assertStatus(400);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_webhook_expired_cancels_once_and_restores_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->cardOrder(User::factory()->create(), $product);

        $this->webhook($this->event('payment_link.expired'))->assertOk();
        $this->webhook($this->event('payment_link.expired'))->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_webhook_ignores_unknown_orders_and_events(): void
    {
        $order = $this->cardOrder(User::factory()->create(), Product::factory()->create());

        $this->webhook($this->event('payment_link.paid', 'plink_unknown'))->assertOk();
        $this->webhook($this->event('payment.captured'))->assertOk();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }
}
