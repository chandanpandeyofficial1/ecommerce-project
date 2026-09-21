<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    // This table has no created_at or updated_at columns.
    public $timestamps = false;

    protected $fillable = ['order_id', 'product_id', 'product_name', 'price', 'quantity'];

    // Price is the amount at the time of the order.
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    // The order this item belongs to.
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // The product that was bought, including soft deleted ones.
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
