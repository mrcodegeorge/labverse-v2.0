<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrderItem extends Model
{
    protected $fillable = [
        'lab_order_id',
        'lab_test_id',
        'sample_id',
        'price',
        'status',
        'assigned_to',
        'entered_by',
        'started_at',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'review_decision',
        'technician_notes',
        'reviewer_comments',
        'has_critical_values',
        'has_abnormal_values',
        'repeat_count',
    ];

    protected $casts = [
        'price' => 'float',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'has_critical_values' => 'boolean',
        'has_abnormal_values' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }
}

