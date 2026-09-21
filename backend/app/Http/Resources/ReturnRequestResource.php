<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    // Shape of a return request; items need order_item loaded for the snapshot.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'reason' => $this->reason,
            'description' => $this->description,
            'photo_url' => $this->photo ? asset('storage/'.$this->photo) : null,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'refund_amount' => $this->refund_amount ? number_format($this->refund_amount, 2, '.', '') : null,
            'refund_method' => $this->refund_method,
            'created_at' => $this->created_at?->toDateTimeString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'order_item_id' => $i->order_item_id,
                'product_name' => $i->orderItem?->product_name,
                'quantity' => $i->quantity,
            ])),
        ];
    }
}
