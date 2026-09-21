<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    // Stock at or below this counts as low.
    public const LOW_STOCK_LIMIT = 10;

    protected $fillable = [
        'category_id', 'name', 'description', 'price', 'stock', 'unit', 'image', 'is_active',
    ];

    // Keep price as a two decimal string and stock as a number.
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // The category this product is listed under.
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
