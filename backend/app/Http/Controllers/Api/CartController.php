<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    // Cart lines and the cart total.
    public function index(Request $request)
    {
        // Lines whose product was deleted or hidden are left out.
        $items = $request->user()->cartItems()->with('product.category')->get()
            ->reject(fn ($i) => $i->product->trashed() || ! $i->product->is_active)->values();
        $total = $items->sum(fn ($i) => $i->product->price * $i->quantity);

        return response()->json([
            'data' => [
                'items' => CartItemResource::collection($items),
                'total' => number_format($total, 2, '.', ''),
            ],
        ]);
    }

    // Add a product, or increase the quantity when it is already in the cart.
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id,deleted_at,NULL',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $this->checkAvailable($product);
        $item = $request->user()->cartItems()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = ($item->quantity ?? 0) + $data['quantity'];

        $this->checkStock($product, $item->quantity);
        $item->save();

        return (new CartItemResource($item->load('product.category')))->response()->setStatusCode(201);
    }

    // Change the quantity of one of the current user cart items.
    public function update(Request $request, CartItem $cartItem)
    {
        $this->ownItem($request, $cartItem);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:100']);

        $this->checkAvailable($cartItem->product);
        $this->checkStock($cartItem->product, $data['quantity']);
        $cartItem->update(['quantity' => $data['quantity']]);

        return new CartItemResource($cartItem->load('product.category'));
    }

    // Remove one of the current user cart items.
    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->ownItem($request, $cartItem);
        $cartItem->delete();

        return response()->json(['message' => 'Removed.']);
    }

    // Items of other users look like they do not exist.
    private function ownItem(Request $request, CartItem $cartItem): void
    {
        abort_if($cartItem->user_id !== $request->user()->id, 404, 'Not found.');
    }

    // Deleted or hidden products cannot be added or changed.
    private function checkAvailable(Product $product): void
    {
        if ($product->trashed() || ! $product->is_active) {
            throw ValidationException::withMessages(['product' => 'This product is no longer available.']);
        }
    }

    // Reject a quantity above the available stock.
    private function checkStock(Product $product, int $quantity): void
    {
        if ($quantity > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$product->stock} of {$product->name} in stock.",
            ]);
        }
    }
}
