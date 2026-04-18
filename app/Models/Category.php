<?php

namespace App\Models;

use App\Concerns\Syncable;
use App\Concerns\Userstampable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasUuids, Syncable, Userstampable;

    protected $fillable = [
        'origin_node_id',
        'name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function originNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'origin_node_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
