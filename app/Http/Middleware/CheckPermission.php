<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Supports pipe-separated permissions (OR logic), e.g. permission:enter_results|approve_result
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your staff account has been deactivated. Please contact your facility administrator.',
            ], 403);
        }

        // Platform administrators bypass permission checks
        if ($user->is_platform_admin) {
            return $next($request);
        }

        $requiredPermissions = explode('|', $permissions);
        foreach ($requiredPermissions as $permission) {
            if ($user->hasPermission(trim($permission))) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have permission to perform this action (' . implode(' or ', $requiredPermissions) . ').',
            'required_permission' => $requiredPermissions,
        ], 403);
    }
}

