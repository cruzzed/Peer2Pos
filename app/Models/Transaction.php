<?php

namespace App\Models;

use App\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasUuids, Syncable;

    protected $fillable = [
        'origin_node_id',
        'cashier_name',
        'total_amount',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function originNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'origin_node_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}
