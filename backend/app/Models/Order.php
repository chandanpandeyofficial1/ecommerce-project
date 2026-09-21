<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // Every status an order can have.
    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id', 'total', 'status', 'address', 'phone', 'payment_method',
        'payment_status', 'payment_reference', 'razorpay_payment_id', 'paid_at',
    ];

    // Keep total as a two decimal string.
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    // Statuses an order may move to from each status.
    const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    // Move the order to a new status. Shared by the API and the admin panel.
    public function changeStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown status.']);
        }

        DB::transaction(function () use ($status) {
            // Re-read the row with a lock so two changes cannot both pass the check.
            $current = self::whereKey($this->id)->lockForUpdate()->value('status');

            if (! in_array($status, self::TRANSITIONS[$current], true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot change an order from {$current} to {$status}.",
                ]);
            }

            // Cancelling puts the stock back.
            if ($status === self::STATUS_CANCELLED) {
                foreach ($this->items()->get() as $item) {
                    Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $this->update(['status' => $status]);
            $this->statusHistories()->create(['status' => $status]);
        });
    }

    // Mark the order paid. Safe to call again: a paid order is left untouched.
    public function markPaid(): void
    {
        DB::transaction(function () {
            $order = self::whereKey($this->id)->lockForUpdate()->first();

            if ($order && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
            }
        });

        $this->refresh();
    }

    // Fail the payment and cancel the order, but only while it is pending and unpaid.
    public function markPaymentFailed(): void
    {
        DB::transaction(function () {
            $order = self::whereKey($this->id)->lockForUpdate()->first();

            if ($order && $order->status === self::STATUS_PENDING && $order->payment_status === 'unpaid') {
                $order->update(['payment_status' => 'failed']);
                // Cancelling restores the stock and writes the history.
                $order->changeStatus(self::STATUS_CANCELLED);
            }
        });

        $this->refresh();
    }

    // The customer who placed the order.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Line items of the order.
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Past statuses of the order, oldest first.
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest();
    }

    // The one return request ever allowed for this order, if any.
    public function returnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class);
    }

    // When this order was marked delivered, or null if it never was.
    public function deliveredAt(): ?\Illuminate\Support\Carbon
    {
        return $this->statusHistories()->where('status', self::STATUS_DELIVERED)->latest('id')->first()?->created_at;
    }
}
