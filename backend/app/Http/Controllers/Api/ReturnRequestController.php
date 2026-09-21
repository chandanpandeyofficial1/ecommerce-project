<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnRequestResource;
use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnRequestController extends Controller
{
    // Window, in days, after the delivered status in which a return can be requested.
    private const WINDOW_DAYS = 3;

    // Own return requests for one own order.
    public function index(Request $request, int $orderId)
    {
        $order = $request->user()->orders()->findOrFail($orderId);

        $requests = $order->returnRequest()->with('items.orderItem')->get();

        return ReturnRequestResource::collection($requests);
    }

    // Request a return for a delivered own order, within the window, once only.
    public function store(Request $request, int $orderId)
    {
        $order = $request->user()->orders()->with('items')->findOrFail($orderId);

        if ($order->status !== Order::STATUS_DELIVERED) {
            throw ValidationException::withMessages(['order' => 'Only delivered orders can be returned.']);
        }

        $deliveredAt = $order->deliveredAt();
        if (! $deliveredAt || floor($deliveredAt->diffInDays(now())) > self::WINDOW_DAYS) {
            throw ValidationException::withMessages(['order' => 'The return window for this order has closed.']);
        }

        if ($order->returnRequest()->exists()) {
            throw ValidationException::withMessages(['order' => 'A return request already exists for this order.']);
        }

        $data = $request->validate([
            'reason' => ['required', Rule::in(ReturnRequest::REASONS)],
            'description' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $orderItems = $order->items->keyBy('id');

        foreach ($data['items'] as $line) {
            $item = $orderItems[$line['order_item_id']] ?? null;
            if (! $item) {
                throw ValidationException::withMessages(['items' => 'One of the items does not belong to this order.']);
            }
            if ($line['quantity'] > $item->quantity) {
                throw ValidationException::withMessages(['items' => "Cannot return more than {$item->quantity} of {$item->product_name}."]);
            }
        }

        $photo = $request->hasFile('photo') ? $request->file('photo')->store('returns', 'public') : null;

        $return = DB::transaction(function () use ($order, $request, $data, $photo) {
            $return = $order->returnRequest()->create([
                'user_id' => $request->user()->id,
                'reason' => $data['reason'],
                'description' => $data['description'],
                'photo' => $photo,
                'status' => ReturnRequest::STATUS_REQUESTED,
            ]);

            foreach ($data['items'] as $line) {
                $return->items()->create([
                    'order_item_id' => $line['order_item_id'],
                    'quantity' => $line['quantity'],
                ]);
            }

            return $return;
        });

        $return->load('items.orderItem');

        return (new ReturnRequestResource($return))->response()->setStatusCode(201);
    }
}
