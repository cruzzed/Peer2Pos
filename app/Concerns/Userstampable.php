<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Userstampable
{
    public static function bootUserstampable(): void
    {
        static::creating(function ($model) {
            if (! $model->isDirty('created_by') && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (! $model->isDirty('updated_by') && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
