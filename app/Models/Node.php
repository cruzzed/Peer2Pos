<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'url',
        'is_self',
        'last_seen_at',
    ];

    protected $casts = [
        'is_self' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function syncStatuses(): HasMany
    {
        return $this->hasMany(SyncStatus::class);
    }

    public static function self(): ?self
    {
        return static::where('is_self', true)->first();
    }

    public static function peers(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_self', false)->get();
    }
}
