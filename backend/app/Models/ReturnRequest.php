<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_RETURNED = 'returned';
    const STATUS_REFUNDED = 'refunded';

    const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
        self::STATUS_REFUNDED,
    ];

    const REASONS = ['wrong_item', 'damaged', 'missing_item', 'quality_problem', 'other'];

    protected $fillable = [
        'order_id', 'user_id', 'reason', 'description', 'photo', 'status',
        'rejection_reason', 'refund_amount', 'refund_method', 'refund_reference',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    // Move from requested to approved, storing the computed refund amount.
    public function approve(string $refundAmount): bool
    {
        if ($this->status !== self::STATUS_REQUESTED) {
            return false;
        }

        return $this->update(['status' => self::STATUS_APPROVED, 'refund_amount' => $refundAmount]);
    }

    // Move from requested to rejected, with a reason for the customer.
    public function reject(string $reason): bool
    {
        if ($this->status !== self::STATUS_REQUESTED) {
            return false;
        }

        return $this->update(['status' => self::STATUS_REJECTED, 'rejection_reason' => $reason]);
    }

    // Move from approved to returned, once the goods are physically back.
    public function markReturned(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        return $this->update(['status' => self::STATUS_RETURNED]);
    }

    // Move from returned to refunded, recording how and with what reference.
    public function refund(string $method, string $reference, string $amount): bool
    {
        if ($this->status !== self::STATUS_RETURNED) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_REFUNDED,
            'refund_method' => $method,
            'refund_reference' => $reference,
            'refund_amount' => $amount,
        ]);
    }
}
