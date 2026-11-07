<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResult extends Model
{
    protected $fillable = [
        'lab_order_item_id',
        'test_parameter_id',
        'result_value',
        'numeric_value',
        'unit',
        'reference_range',
        'flag',
        'is_draft',
        'remarks',
        'entered_by',
        'updated_by',
    ];

    protected $casts = [
        'numeric_value' => 'float',
        'is_draft' => 'boolean',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(TestParameter::class, 'test_parameter_id');
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

