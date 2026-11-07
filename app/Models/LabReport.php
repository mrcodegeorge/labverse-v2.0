<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabReport extends Model
{
    protected $fillable = [
        'report_number',
        'lab_order_id',
        'patient_id',
        'branch_id',
        'status',
        'verification_code',
        'pathologist_comment',
        'clinical_interpretation',
        'generated_by',
        'approved_by',
        'released_at',
        'download_count',
    ];

    protected $casts = [
        'released_at' => 'datetime',
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

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

