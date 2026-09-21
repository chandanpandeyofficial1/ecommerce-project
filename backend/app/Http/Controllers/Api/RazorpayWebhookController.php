<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    // Receive Razorpay events after checking the signature of the raw body.
    public function handle(Request $request)
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature');

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || $signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook.'], 400);
        }

        $linkId = $request->input('payload.payment_link.entity.id');
        $order = $linkId ? Order::where('payment_reference', $linkId)->first() : null;

        // Unknown orders and other events are acknowledged and ignored.
        if ($order) {
            // Razorpay includes the payment entity on a paid event; keep its id for later refunds.
            $paymentId = $request->input('payload.payment.entity.id');
            if ($paymentId && ! $order->razorpay_payment_id) {
                $order->update(['razorpay_payment_id' => $paymentId]);
            }

            match ($request->input('event')) {
                'payment_link.paid' => $order->markPaid(),
                'payment_link.expired', 'payment_link.cancelled' => $order->markPaymentFailed(),
                default => null,
            };
        }

        return response()->json(['received' => true]);
    }
}
