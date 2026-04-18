<?php

namespace App\Models;

use App\Concerns\Syncable;
use App\Concerns\Userstampable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids, Syncable, Userstampable;

    protected $fillable = [
        'origin_node_id',
        'item_code',
        'category_id',
        'supplier_id',
        'location_id',
        'name',
        'stock_qty',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'stock_qty' => 'integer',
        'is_active' => 'boolean',
    ];

    public function originNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'origin_node_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function qtyTypes(): HasMany
    {
        return $this->hasMany(QtyType::class);
    }

    public function baseQtyType(): HasMany
    {
        return $this->hasMany(QtyType::class)->where('is_base', true);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}
