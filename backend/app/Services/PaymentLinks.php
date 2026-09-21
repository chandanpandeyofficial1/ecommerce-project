<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class PaymentLinks
{
    private const BASE = 'https://api.razorpay.com/v1';

    // Create a Razorpay payment link for the order and return its id and hosted page url.
    public function createLink(Order $order): array
    {
        $user = $order->user;
        $customer = array_filter([
            'name' => $user?->name,
            'email' => $user?->email,
            // Razorpay rejects odd phone formats, so send digits only.
            'contact' => preg_replace('/\D/', '', (string) $order->phone),
        ]);

        $response = $this->client()->post(self::BASE.'/payment_links', [
            'amount' => self::toPaise($order->total),
            'currency' => 'INR',
            'accept_partial' => false,
            // A new reference for every attempt, at most 40 characters.
            'reference_id' => 'ord'.$order->id.'-'.time(),
            'description' => 'Order #'.$order->id,
            'customer' => $customer,
            'notify' => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
            // Razorpay needs at least 15 minutes; use 30.
            'expire_by' => time() + 30 * 60,
            'callback_url' => $this->base().'/payment/callback',
            'callback_method' => 'get',
            'notes' => ['order_id' => (string) $order->id],
        ])->throw()->json();

        return ['id' => $response['id'], 'url' => $response['short_url']];
    }

    // Ask Razorpay for the current status of a payment link (created, paid, expired, cancelled).
    public function fetchStatus(string $linkId): string
    {
        return (string) $this->client()->get(self::BASE.'/payment_links/'.$linkId)->throw()->json('status');
    }

    // Refund a captured payment, in paise. Returns the refund id and its status.
    public function refund(string $paymentId, int $amountPaise): array
    {
        $response = $this->client()->post(self::BASE.'/payments/'.$paymentId.'/refund', [
            'amount' => $amountPaise,
        ])->throw()->json();

        return ['id' => $response['id'], 'status' => $response['status']];
    }

    // Convert a price like "12.50" to 1250 using string handling, no float multiplication.
    public static function toPaise(string|int|float $amount): int
    {
        return (int) str_replace('.', '', number_format((float) $amount, 2, '.', ''));
    }

    // HTTP client signed with the key id and secret.
    private function client()
    {
        return Http::withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->acceptJson()->timeout(15);
    }

    // Base url for the return page.
    private function base(): string
    {
        return rtrim(config('services.razorpay.return_base') ?: config('app.url'), '/');
    }
}
