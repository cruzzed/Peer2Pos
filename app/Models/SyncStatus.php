<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncStatus extends Model
{
    protected $fillable = [
        'syncable_type',
        'syncable_id',
        'node_id',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function markSynced(): void
    {
        $this->update(['status' => 'synced', 'synced_at' => now()]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
