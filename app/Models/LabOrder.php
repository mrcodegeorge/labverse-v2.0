<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabOrder extends Model
{
    protected $fillable = [
        'order_number',
        'patient_id',
        'branch_id',
        'priority',
        'referring_doctor',
        'referring_facility',
        'clinical_notes',
        'diagnosis',
        'subtotal',
        'discount_amount',
        'discount_reason',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_status',
        'status',
        'ordered_by',
        'ordered_at',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'total_amount' => 'float',
        'amount_paid' => 'float',
        'balance_due' => 'float',
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(LabReport::class);
    }
}

