<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit',
        'branch_id',
        'quantity_on_hand',
        'reorder_level',
        'unit_cost',
        'supplier_name',
        'supplier_contact',
        'batch_number',
        'expiry_date',
        'storage_condition',
        'location_bin',
        'is_active',
    ];

    protected $casts = [
        'quantity_on_hand' => 'float',
        'reorder_level' => 'float',
        'unit_cost' => 'float',
        'expiry_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $appends = ['stock_status', 'is_expiring_soon'];

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity_on_hand <= 0) {
            return 'out_of_stock';
        }
        if ($this->quantity_on_hand <= $this->reorder_level) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return Carbon::parse($this->expiry_date)->lte(Carbon::now()->addDays(45));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}

