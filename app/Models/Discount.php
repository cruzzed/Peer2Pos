<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    protected $fillable = [
        'qty_type_id',
        'enabled',
        'type',
        'value',
        'timed',
        'date_start',
        'date_end',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'value' => 'decimal:2',
        'timed' => 'boolean',
        'date_start' => 'date',
        'date_end' => 'date',
    ];

    public function qtyType(): BelongsTo
    {
        return $this->belongsTo(QtyType::class);
    }
}
