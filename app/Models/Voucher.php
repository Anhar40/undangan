<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_purchase',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isWithinPeriod(): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasRemainingUses(): bool
    {
        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }

    public function isAvailableForAmount(int|float|string $amount): bool
    {
        if (! $this->is_active || ! $this->isWithinPeriod() || ! $this->hasRemainingUses()) {
            return false;
        }

        $min = $this->min_purchase !== null ? (float) $this->min_purchase : 0.0;

        return (float) $amount >= $min;
    }

    /**
     * Calculate discount in whole rupiah (integer).
     */
    public function discountFor(int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $discount = $this->type === 'percent'
            ? (int) floor($amount * ((float) $this->value / 100))
            : (int) floor((float) $this->value);

        $discount = max(0, min($discount, $amount));

        return $discount;
    }
}
