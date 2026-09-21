<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    // Shape of a product in the API, with a full image URL.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->category?->name,
            'name' => $this->name,
            'description' => $this->description,
            'price' => number_format($this->price, 2, '.', ''),
            'stock' => $this->stock,
            'unit' => $this->unit,
            'image_url' => $this->image ? asset('storage/'.$this->image) : null,
        ];
    }
}
