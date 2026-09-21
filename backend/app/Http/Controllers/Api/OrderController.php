<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\PaymentLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // Own orders, newest first.
    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with('items')->latest('id')->paginate(10)
        );
    }

    // One own order with items and history.
    public function show(Request $request, int $id, PaymentLinks $links)
    {
        $order = $request->user()->orders()->findOrFail($id);

        $this->syncPayment($order, $links);

        $order->load(['items', 'statusHistories']);

        return new OrderResource($order);
    }

    // Place an order from the cart or as a direct buy.
    public function store(Request $request, PaymentLinks $links)
    {
        // Cast first so "0"/false are read as a real boolean by the rules below.
        $request->merge(['use_cart' => $request->boolean('use_cart')]);

        // Cart order needs no product fields; a direct buy needs both.
        $data = $request->validate([
            'address' => 'required|string|min:5|max:500',
            'phone' => ['required', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'use_cart' => 'boolean',
            'payment_method' => ['nullable', Rule::in(['cod', 'card'])],
            'product_id' => 'required_if:use_cart,false|prohibited_if:use_cart,true|integer|exists:products,id,deleted_at,NULL',
            'quantity' => 'required_if:use_cart,false|prohibited_if:use_cart,true|integer|min:1|max:100',
        ], ['phone.regex' => 'Enter a valid phone number']);

        $user = $request->user();
        $useCart = $request->boolean('use_cart');
        $method = $data['payment_method'] ?? 'cod';

        $order = DB::transaction(function () use ($data, $user, $useCart, $method) {
            // Build the list of product id => quantity to buy.
            if ($useCart) {
                $wanted = $user->cartItems()->pluck('quantity', 'product_id')->all();
            } else {
                $wanted = [$data['product_id'] => $data['quantity']];
            }

            if (! $wanted) {
                throw ValidationException::withMessages(['use_cart' => 'Your cart is empty.']);
            }

            // Lock the rows so two orders cannot take the same stock.
            $products = Product::withTrashed()->whereIn('id', array_keys($wanted))->lockForUpdate()->get()->keyBy('id');

            $total = 0;
            foreach ($wanted as $productId => $qty) {
                $product = $products[$productId] ?? null;
                // A deleted, hidden or missing product cannot be bought.
                if (! $product || $product->trashed() || ! $product->is_active) {
                    $name = $product->name ?? 'A product';
                    throw ValidationException::withMessages(['product' => "{$name} is no longer available."]);
                }
                if ($product->stock < $qty) {
                    $name = $product->name;
                    throw ValidationException::withMessages(['stock' => "Not enough stock for {$name}."]);
                }
                $total += $product->price * $qty;
            }

            $order = $user->orders()->create([
                'total' => $total,
                'status' => Order::STATUS_PENDING,
                'address' => $data['address'],
                'phone' => $data['phone'],
                'payment_method' => $method,
                'payment_status' => 'unpaid',
            ]);

            foreach ($wanted as $productId => $qty) {
                $product = $products[$productId];
                // Copy name and price so later edits do not change this order.
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $qty,
                ]);
                $product->decrement('stock', $qty);
            }

            $order->statusHistories()->create(['status' => Order::STATUS_PENDING]);

            if ($useCart) {
                $user->cartItems()->delete();
            }

            return $order;
        });

        $url = null;

        // Card orders get a payment link after the order is safely saved.
        if ($method === 'card') {
            $url = $this->startPayment($order->load('items'), $links);
            if ($url instanceof JsonResponse) {
                return $url;
            }
        }

        $order->refresh()->load(['items', 'statusHistories']);

        return (new OrderResource($order))->additional(['checkout_url' => $url])->response()->setStatusCode(201);
    }

    // Pay for an own pending, unpaid card order with a fresh payment link.
    public function pay(Request $request, int $id, PaymentLinks $links)
    {
        $order = $request->user()->orders()->with('items')->findOrFail($id);

        if ($order->payment_method !== 'card') {
            throw ValidationException::withMessages(['payment' => 'This is not a card order.']);
        }
        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages(['payment' => 'This order is already paid.']);
        }
        if ($order->status !== Order::STATUS_PENDING || $order->payment_status !== 'unpaid') {
            throw ValidationException::withMessages(['payment' => 'This order is no longer pending, so it cannot be paid.']);
        }

        $url = $this->startPayment($order, $links, false);
        if ($url instanceof JsonResponse) {
            return $url;
        }

        return (new OrderResource($order->refresh()->load(['items', 'statusHistories'])))->additional(['checkout_url' => $url]);
    }

    // Create the payment link and remember its id; on failure optionally cancel the order and return an error.
    private function startPayment(Order $order, PaymentLinks $links, bool $cancelOnFail = true): string|JsonResponse
    {
        try {
            $link = $links->createLink($order);
            $order->update(['payment_reference' => $link['id']]);

            return $link['url'];
        } catch (\Throwable $e) {
            report($e);
            // Put the stock back so the customer is not left with a reserved order.
            if ($cancelOnFail) {
                $order->changeStatus(Order::STATUS_CANCELLED);
            }

            return response()->json(['message' => 'Card payment could not be started. Please try again.'], 502);
        }
    }

    // Ask the gateway about a waiting card order, so payment is picked up without a public webhook.
    private function syncPayment(Order $order, PaymentLinks $links): void
    {
        if ($order->payment_method !== 'card' || $order->status !== Order::STATUS_PENDING
            || $order->payment_status !== 'unpaid' || ! $order->payment_reference) {
            return;
        }

        // A gateway error must never break reading the order.
        try {
            $status = $links->fetchStatus($order->payment_reference);

            if ($status === 'paid') {
                $order->markPaid();
            } elseif (in_array($status, ['expired', 'cancelled'], true)) {
                $order->markPaymentFailed();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // Customers may cancel their own order while it is pending.
    public function cancel(Request $request, int $id)
    {
        $order = $request->user()->orders()->findOrFail($id);

        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending orders can be cancelled.']);
        }

        $order->changeStatus(Order::STATUS_CANCELLED);

        return new OrderResource($order->load(['items', 'statusHistories']));
    }
}
