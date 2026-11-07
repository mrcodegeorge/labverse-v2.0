<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'branch_id',
        'movement_type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reference_number',
        'reason',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'previous_quantity' => 'float',
        'new_quantity' => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

