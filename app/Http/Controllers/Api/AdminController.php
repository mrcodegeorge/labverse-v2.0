<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\LabReport;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sample;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use EnforcesBranchAccess;

    // =========================================================================
    // MULTI-BRANCH MANAGEMENT
    // =========================================================================
    public function branches(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Branch::withCount(['users', 'patients', 'orders', 'samples', 'inventoryItems']);
        if (!$user->is_platform_admin && !$user->hasPermission('manage_branches')) {
            $query->where('id', $user->branch_id);
        }

        $branches = $query->orderByDesc('is_main')->orderBy('name')->get()->map(function (Branch $branch) {
            $branch->revenue_total = (float) Payment::where('branch_id', $branch->id)->where('status', 'completed')->sum('amount');
            $branch->released_reports_count = LabReport::where('branch_id', $branch->id)->where('status', 'released')->count();
            return $branch;
        });

        return response()->json([
            'branches' => $branches,
        ]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:150'],
            'region' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::create([
            ...$validated,
            'is_main' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditService::log(
            $request,
            'branch.created',
            'Branches',
            "Created laboratory branch {$branch->name} ({$branch->code}) in {$branch->city}.",
            'Branch',
            $branch->id,
            $branch->code,
            null,
            $branch->id
        );

        return response()->json([
            'message' => 'Laboratory branch created successfully.',
            'branch' => $branch,
        ], 201);
    }

    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code,' . $branch->id],
            'name' => ['required', 'string', 'max:150'],
            'region' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch->update($validated);

        AuditService::log(
            $request,
            'branch.updated',
            'Branches',
            "Updated branch details for {$branch->name} ({$branch->code}).",
            'Branch',
            $branch->id,
            $branch->code,
            null,
            $branch->id
        );

        return response()->json([
            'message' => 'Branch configuration updated successfully.',
            'branch' => $branch,
        ]);
    }

    // =========================================================================
    // STAFF & CUSTOM RBAC MANAGEMENT
    // =========================================================================
    public function staff(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        $query = User::with(['branch:id,code,name,city', 'roles.permissions']);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('staff_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        if ($roleId = $request->input('role_id')) {
            $query->whereHas('roles', fn ($r) => $r->where('roles.id', $roleId));
        }

        $users = $query->orderBy('id')->get();
        $roles = Role::with('permissions')->withCount('users')->orderBy('id')->get();
        $permissions = Permission::orderBy('category')->orderBy('id')->get();

        return response()->json([
            'staff' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'permissions_by_category' => $permissions->groupBy('category'),
        ]);
    }

    public function storeStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'job_title' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:60'],
            'branch_id' => ['required', 'exists:branches,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $nextSeq = (User::max('id') ?? 0) + 1;
        $staffId = sprintf('STF-%04d', $nextSeq);

        $staff = User::create([
            'staff_id' => $staffId,
            'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'job_title' => $validated['job_title'],
            'department' => $validated['department'] ?? 'Laboratory Operations',
            'license_number' => $validated['license_number'] ?? null,
            'branch_id' => $validated['branch_id'],
            'is_platform_admin' => false,
            'is_active' => $validated['is_active'] ?? true,
            'password' => $validated['password'],
        ]);

        $staff->roles()->sync($validated['role_ids']);
        $staff->load(['branch:id,code,name', 'roles.permissions']);

        AuditService::log(
            $request,
            'staff.created',
            'RBAC',
            "Registered staff member {$staff->name} ({$staff->staff_id}) assigned to {$staff->branch?->name}.",
            'User',
            $staff->id,
            $staff->staff_id,
            ['roles' => $staff->roles->pluck('slug')],
            $staff->branch_id
        );

        return response()->json([
            'message' => 'Staff account created and roles assigned.',
            'staff' => $staff,
        ], 201);
    }

    public function updateStaff(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'string', 'max:40'],
            'job_title' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:60'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['required', 'boolean'],
        ]);

        $updateData = [
            'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'job_title' => $validated['job_title'],
            'department' => $validated['department'] ?? $user->department,
            'license_number' => $validated['license_number'] ?? $user->license_number,
            'branch_id' => $validated['branch_id'] ?? $user->branch_id,
            'is_active' => $validated['is_active'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        $user->update($updateData);
        $user->roles()->sync($validated['role_ids']);
        $user->load(['branch:id,code,name', 'roles.permissions']);

        AuditService::log(
            $request,
            'staff.updated',
            'RBAC',
            "Updated staff account and role assignments for {$user->name} ({$user->staff_id}).",
            'User',
            $user->id,
            $user->staff_id,
            ['is_active' => $user->is_active, 'roles' => $user->roles->pluck('slug')],
            $user->branch_id
        );

        return response()->json([
            'message' => 'Staff account updated successfully.',
            'staff' => $user,
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $role->description,
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('permissions');

        AuditService::log(
            $request,
            'role.permissions_updated',
            'RBAC',
            "Updated permission matrix for role '{$role->name}' ({$role->permissions->count()} permissions active).",
            'Role',
            $role->id,
            $role->slug
        );

        return response()->json([
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'role' => $role,
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('permissions');

        AuditService::log(
            $request,
            'role.created',
            'RBAC',
            "Created custom role '{$role->name}' ({$role->slug}).",
            'Role',
            $role->id,
            $role->slug
        );

        return response()->json([
            'message' => 'Custom role created successfully.',
            'role' => $role,
        ], 201);
    }

    // =========================================================================
    // AUDIT LOGS, NOTIFICATIONS & SETTINGS
    // =========================================================================
    public function auditLogs(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        $query = AuditLog::with(['user:id,name,staff_id,job_title', 'branch:id,code,name']);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('target_reference', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(25));
    }

    public function notifications(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        $notifications = SystemNotification::when($branchId, fn ($q) => $q->where(function ($sub) use ($branchId) {
                $sub->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->latest()
            ->limit(25)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $notifications->where('is_read', false)->count(),
        ]);
    }

    public function markNotificationRead(Request $request, SystemNotification $notification): JsonResponse
    {
        $notification->update(['is_read' => true]);
        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);
        SystemNotification::when($branchId, fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function settings(Request $request): JsonResponse
    {
        $settings = SystemSetting::pluck('value', 'key');
        return response()->json(['settings' => $settings]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'group' => 'facility']
            );
        }

        AuditService::log(
            $request,
            'settings.updated',
            'Settings',
            'Updated laboratory facility & clinical policy configuration.'
        );

        return response()->json([
            'message' => 'Laboratory system settings saved successfully.',
            'settings' => SystemSetting::pluck('value', 'key'),
        ]);
    }
}

