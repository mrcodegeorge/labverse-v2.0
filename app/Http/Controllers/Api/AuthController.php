<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['branch', 'roles.permissions'])
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our laboratory staff records.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your staff account is currently deactivated. Please contact your facility administrator.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('labverse-api-token')->plainTextToken;

        AuditService::log(
            $request,
            'auth.login',
            'Authentication',
            "Staff member {$user->name} ({$user->staff_id}) signed in.",
            'User',
            $user->id,
            $user->staff_id,
            null,
            $user->branch_id
        );

        return response()->json([
            'message' => 'Authenticated successfully.',
            'token' => $token,
            'user' => $this->formatUserPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['branch', 'roles.permissions']);

        return response()->json([
            'user' => $this->formatUserPayload($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Your current password does not match.'],
                ]);
            }
            $user->password = $validated['new_password'];
        }

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->name = trim("{$validated['first_name']} {$validated['last_name']}");
        $user->phone = $validated['phone'] ?? $user->phone;
        $user->job_title = $validated['job_title'] ?? $user->job_title;
        $user->save();

        $user->load(['branch', 'roles.permissions']);

        return response()->json([
            'message' => 'Account profile updated successfully.',
            'user' => $this->formatUserPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            AuditService::log(
                $request,
                'auth.logout',
                'Authentication',
                "Staff member {$user->name} signed out.",
                'User',
                $user->id,
                $user->staff_id,
                null,
                $user->branch_id
            );

            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }

    public function demoAccounts(): JsonResponse
    {
        $users = User::with(['branch', 'roles'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'staff_id' => $u->staff_id,
                'name' => $u->name,
                'email' => $u->email,
                'job_title' => $u->job_title,
                'branch' => $u->branch?->name ?? 'All Branches (Platform)',
                'roles' => $u->roles->pluck('name')->values(),
                'is_platform_admin' => $u->is_platform_admin,
            ]);

        return response()->json([
            'accounts' => $users,
        ]);
    }

    public function formatUserPayload(User $user): array
    {
        $canSwitchBranches = $user->is_platform_admin || $user->hasPermission('manage_branches');
        $accessibleBranches = $canSwitchBranches
            ? Branch::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(['id', 'code', 'name', 'city', 'is_main'])
            : Branch::where('id', $user->branch_id)->get(['id', 'code', 'name', 'city', 'is_main']);

        return [
            'id' => $user->id,
            'staff_id' => $user->staff_id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'license_number' => $user->license_number,
            'branch_id' => $user->branch_id,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'code' => $user->branch->code,
                'name' => $user->branch->name,
                'city' => $user->branch->city,
            ] : null,
            'is_platform_admin' => $user->is_platform_admin,
            'is_active' => $user->is_active,
            'can_switch_branches' => $canSwitchBranches,
            'accessible_branches' => $accessibleBranches,
            'roles' => $user->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ])->values(),
            'permissions' => $user->getAllPermissionSlugs()->values(),
        ];
    }
}

