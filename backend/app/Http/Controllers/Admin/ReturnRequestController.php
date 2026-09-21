<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\PaymentLinks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    // Paginated list with an optional status filter.
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(ReturnRequest::STATUSES)]]);

        $requests = ReturnRequest::with(['order.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.returns.index', [
            'requests' => $requests,
            'statuses' => ReturnRequest::STATUSES,
            'counts' => ReturnRequest::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    // Detail with items, order, customer and history.
    public function show(ReturnRequest $returnRequest)
    {
        $returnRequest->load(['order.user', 'items.orderItem']);

        return view('admin.returns.show', ['returnRequest' => $returnRequest]);
    }

    // Approve: refund amount is computed from the order snapshot, never editable by the admin.
    public function approve(ReturnRequest $returnRequest)
    {
        $returnRequest->load('items.orderItem');

        $amount = $returnRequest->items->sum(fn ($i) => $i->orderItem->price * $i->quantity);

        if (! $returnRequest->approve(number_format($amount, 2, '.', ''))) {
            return back()->with('error', 'This request can no longer be approved.');
        }

        return back()->with('success', 'Return request approved. Refund amount: ₹'.number_format($amount, 2));
    }

    // Reject: a reason is required so the customer sees why.
    public function reject(Request $request, ReturnRequest $returnRequest)
    {
        $data = $request->validate(['rejection_reason' => 'required|string|max:1000']);

        if (! $returnRequest->reject($data['rejection_reason'])) {
            return back()->with('error', 'This request can no longer be rejected.');
        }

        return back()->with('success', 'Return request rejected.');
    }

    // Mark the goods as physically returned. Stock is not touched here.
    public function markReturned(ReturnRequest $returnRequest)
    {
        if (! $returnRequest->markReturned()) {
            return back()->with('error', 'This request must be approved first.');
        }

        return back()->with('success', 'Marked as returned.');
    }

    // Refund: online via Razorpay when a payment id is on record, otherwise a manual reference.
    public function refund(Request $request, ReturnRequest $returnRequest, PaymentLinks $links)
    {
        $order = $returnRequest->order;
        $amount = (string) $returnRequest->refund_amount;

        if ($returnRequest->status !== ReturnRequest::STATUS_RETURNED) {
            return back()->with('error', 'The item must be marked returned before it can be refunded.');
        }

        $canRefundOnline = $order->payment_method === 'card' && $order->razorpay_payment_id;

        if ($canRefundOnline) {
            try {
                $result = $links->refund($order->razorpay_payment_id, PaymentLinks::toPaise($amount));
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'The refund could not be processed. Please try again.');
            }

            $returnRequest->refund('razorpay', $result['id'], $amount);

            return back()->with('success', 'Refund issued via Razorpay.');
        }

        $data = $request->validate(['refund_reference' => 'required|string|max:255']);
        $returnRequest->refund('manual', $data['refund_reference'], $amount);

        return back()->with('success', 'Manual refund recorded.');
    }
}
