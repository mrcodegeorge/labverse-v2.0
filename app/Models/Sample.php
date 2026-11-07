<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sample extends Model
{
    protected $fillable = [
        'sample_code',
        'barcode',
        'lab_order_id',
        'patient_id',
        'branch_id',
        'sample_type',
        'container_type',
        'collection_site',
        'status',
        'collected_by',
        'collected_at',
        'received_by',
        'received_at',
        'accepted_by',
        'accepted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'rejection_notes',
        'storage_location',
        'condition_on_arrival',
        'notes',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'received_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function collectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }
}

