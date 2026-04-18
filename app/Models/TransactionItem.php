<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_name',
        'qty_type_id',
        'qty_type_name',
        'unit_price',
        'qty',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function qtyType(): BelongsTo
    {
        return $this->belongsTo(QtyType::class);
    }
}
