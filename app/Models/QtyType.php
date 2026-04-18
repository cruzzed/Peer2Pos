<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QtyType extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'name',
        'qty',
        'barcode',
        'base_price',
        'selling_price',
        'is_base',
    ];

    protected $casts = [
        'qty' => 'integer',
        'base_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_base' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function discount(): HasOne
    {
        return $this->hasOne(Discount::class);
    }

    /**
     * Resolve the effective price after applying any active discount.
     */
    public function effectivePrice(): float
    {
        $price = (float) $this->selling_price;
        $discount = $this->discount;

        if (! $discount || ! $discount->enabled) {
            return $price;
        }

        if ($discount->timed) {
            $today = now()->toDateString();
            if ($discount->date_start && $today < $discount->date_start) {
                return $price;
            }
            if ($discount->date_end && $today > $discount->date_end) {
                return $price;
            }
        }

        if ($discount->type === 'percent') {
            return round($price * (1 - $discount->value / 100), 2);
        }

        return max(0, round($price - $discount->value, 2));
    }
}
