<?php

namespace App\Models;

use App\Concerns\Syncable;
use App\Concerns\Userstampable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasUuids, Syncable, Userstampable;

    protected $fillable = [
        'origin_node_id',
        'name',
        'created_by',
        'updated_by',
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
