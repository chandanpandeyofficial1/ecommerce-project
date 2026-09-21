<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // Paginated list with an optional status filter.
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(Order::STATUSES)]]);

        $orders = Order::with('user', 'returnRequest')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'counts' => Order::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    // Order detail with items and status history.
    public function show(Order $order)
    {
        $order->load(['user', 'items', 'statusHistories', 'returnRequest']);

        return view('admin.orders.show', [
            'order' => $order,
            'nextStatuses' => $this->allowedStatuses($order),
        ]);
    }

    // Next statuses, limited to cancelled for unpaid card orders.
    private function allowedStatuses(Order $order): array
    {
        $next = Order::TRANSITIONS[$order->status];

        if ($order->payment_method === 'card' && $order->payment_status !== 'paid') {
            return array_values(array_intersect($next, [Order::STATUS_CANCELLED]));
        }

        return $next;
    }

    // Change the status through the model so the rules and stock handling stay in one place.
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|string']);

        // An unpaid card order may only be cancelled.
        if ($order->payment_method === 'card' && $order->payment_status !== 'paid' && $request->status !== Order::STATUS_CANCELLED) {
            return back()->with('error', 'This card order is not paid yet, so it can only be cancelled.');
        }

        try {
            $order->changeStatus($request->status);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Order status updated.');
    }
}
