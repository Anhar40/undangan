<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invitation_id',
        'voucher_id',
        'voucher_code',
        'order_id',
        'idempotency_key',
        'replaced_by_order_id',
        'gross_amount',
        'original_amount',
        'voucher_discount_amount',
        'payment_method',
        'payment_type',
        'payment_label',
        'payment_reference',
        'payment_instructions',
        'payment_expires_at',
        'transaction_status',
        'fraud_status',
        'midtrans_transaction_id',
        'snap_token',
        'snap_url',
        'transaction_time',
        'paid_at',
        'voucher_redeemed_at',
        'verified_at',
        'notification_count',
        'midtrans_response',
    ];

    protected function casts(): array
    {
        return [
            'transaction_status' => PaymentStatus::class,
            'gross_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'voucher_discount_amount' => 'decimal:2',
            'transaction_time' => 'datetime',
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'voucher_redeemed_at' => 'datetime',
            'verified_at' => 'datetime',
            'notification_count' => 'integer',
            'payment_instructions' => 'array',
            'midtrans_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function isSuccessful(): bool
    {
        return $this->transaction_status?->isSuccessful() ?? false;
    }

    public function isPaymentExpired(): bool
    {
        return $this->payment_expires_at?->isPast() ?? false;
    }

    /** True when the customer abandoned this order and chose another method. */
    public function wasReplacedForMethodChange(): bool
    {
        return (bool) ($this->midtrans_response['cancelled_for_method_change'] ?? false);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('transaction_status', [
            PaymentStatus::Paid->value,
            PaymentStatus::Settlement->value,
        ]);
    }
}
