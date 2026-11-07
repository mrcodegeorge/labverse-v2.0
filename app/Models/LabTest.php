<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTest extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category_id',
        'sample_type',
        'container_type',
        'sample_volume',
        'price',
        'nhis_tariff',
        'turnaround_hours',
        'methodology',
        'clinical_indications',
        'preparation_instructions',
        'requires_fasting',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'nhis_tariff' => 'float',
        'requires_fasting' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class, 'category_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(TestParameter::class)->orderBy('sort_order');
    }
}

