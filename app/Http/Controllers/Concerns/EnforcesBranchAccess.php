<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait EnforcesBranchAccess
{
    protected function resolveEffectiveBranchId(Request $request, ?int $requestedBranchId = null): ?int
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $branchIdFromRequest = $requestedBranchId
            ?? ($request->filled('branch_id') && $request->input('branch_id') !== 'all'
                ? (int) $request->input('branch_id')
                : ($request->header('X-Branch-Id') && $request->header('X-Branch-Id') !== 'all'
                    ? (int) $request->header('X-Branch-Id')
                    : null));

        // Platform admins or users with manage_branches can view all branches or filter by any branch
        if ($user->is_platform_admin || $user->hasPermission('manage_branches')) {
            return $branchIdFromRequest;
        }

        // Standard staff are strictly scoped to their assigned branch
        return $user->branch_id;
    }

    protected function scopeBranch(Builder $query, Request $request, string $column = 'branch_id'): Builder
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        if ($branchId !== null) {
            $query->where($column, $branchId);
        }

        return $query;
    }

    protected function authorizeBranchRecord(Request $request, ?int $recordBranchId): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->is_platform_admin || $user->hasPermission('manage_branches')) {
            return;
        }

        if ($recordBranchId !== null && (int) $user->branch_id !== (int) $recordBranchId) {
            abort(403, 'Access denied: Record belongs to a different laboratory branch.');
        }
    }
}

