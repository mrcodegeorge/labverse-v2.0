<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'staff_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'department',
        'license_number',
        'branch_id',
        'is_platform_admin',
        'is_active',
        'last_login_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function hasRole(string $slug): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->roles->contains('slug', $slug);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->getAllPermissionSlugs()->contains($permissionSlug);
    }

    public function getAllPermissionSlugs(): \Illuminate\Support\Collection
    {
        if ($this->is_platform_admin) {
            return Permission::pluck('slug');
        }

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();
    }
}
