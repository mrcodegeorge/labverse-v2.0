<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestParameter extends Model
{
    protected $fillable = [
        'lab_test_id',
        'code',
        'name',
        'input_type',
        'unit',
        'ref_min_male',
        'ref_max_male',
        'ref_min_female',
        'ref_max_female',
        'critical_low',
        'critical_high',
        'reference_text',
        'options_json',
        'sort_order',
        'is_mandatory',
    ];

    protected $casts = [
        'ref_min_male' => 'float',
        'ref_max_male' => 'float',
        'ref_min_female' => 'float',
        'ref_max_female' => 'float',
        'critical_low' => 'float',
        'critical_high' => 'float',
        'options_json' => 'array',
        'is_mandatory' => 'boolean',
    ];

    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class);
    }
}

