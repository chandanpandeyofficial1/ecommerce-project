<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    // Shape of an order; items and history appear only when loaded.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => number_format($this->total, 2, '.', ''),
            'address' => $this->address,
            'phone' => $this->phone,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_name' => $i->product_name,
                'price' => number_format($i->price, 2, '.', ''),
                'quantity' => $i->quantity,
                'line_total' => number_format($i->price * $i->quantity, 2, '.', ''),
            ])),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'status' => $h->status,
                'at' => $h->created_at?->toDateTimeString(),
            ])),
        ];
    }
}
