<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    // Razorpay sends the customer here after the hosted page; check the signature before trusting it.
    public function handle(Request $request)
    {
        $linkId = (string) $request->query('razorpay_payment_link_id');
        $reference = (string) $request->query('razorpay_payment_link_reference_id');
        $status = (string) $request->query('razorpay_payment_link_status');
        $paymentId = (string) $request->query('razorpay_payment_id');
        $signature = (string) $request->query('razorpay_signature');

        $secret = (string) config('services.razorpay.key_secret');
        $expected = hash_hmac('sha256', "{$linkId}|{$reference}|{$status}|{$paymentId}", $secret);

        $valid = $secret !== '' && $signature !== '' && hash_equals($expected, $signature);

        if ($valid && $status === 'paid') {
            $order = Order::where('payment_reference', $linkId)->first();
            // Razorpay's own payment id, kept so a refund can be issued against this exact payment later.
            if ($order && ! $order->razorpay_payment_id && $paymentId !== '') {
                $order->update(['razorpay_payment_id' => $paymentId]);
            }
            $order?->markPaid();

            return view('payment-result', [
                'title' => 'Payment received', 'icon' => 'bi-check-circle',
                'message' => 'Payment received. Taking you back to the app.',
                'appLink' => $this->appLink($order?->id, 'paid'),
            ]);
        }

        return view('payment-result', [
            'title' => 'Payment not completed', 'icon' => 'bi-x-circle',
            'message' => 'Payment not completed. Go back to the app and try again.',
            'appLink' => $this->appLink(null, 'failed'),
        ]);
    }

    // Link that reopens the mobile app; the app then re-checks the order itself.
    private function appLink(?int $orderId, string $status): string
    {
        return 'minigrocery://payment/result?status='.$status.($orderId ? '&order='.$orderId : '');
    }
}
