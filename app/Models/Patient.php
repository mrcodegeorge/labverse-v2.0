<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'patient_code',
        'first_name',
        'last_name',
        'other_names',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'address',
        'city',
        'nationality',
        'national_id',
        'nhis_number',
        'blood_group',
        'genotype',
        'allergies',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'branch_id',
        'registered_by',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $appends = ['full_name', 'age'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->other_names} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? Carbon::parse($this->date_of_birth)->age : null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function registeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(LabReport::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

