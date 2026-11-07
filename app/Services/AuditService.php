<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SystemNotification;
use Illuminate\Http\Request;

class AuditService
{
    public static function log(
        ?Request $request,
        string $action,
        string $module,
        string $description,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetReference = null,
        ?array $metadata = null,
        ?int $branchId = null
    ): AuditLog {
        $user = $request?->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'branch_id' => $branchId ?? $user?->branch_id,
            'action' => $action,
            'module' => $module,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_reference' => $targetReference,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request?->ip() ?? '127.0.0.1',
        ]);
    }

    public static function notify(
        string $title,
        string $message,
        string $type = 'info',
        string $category = 'Clinical',
        ?int $branchId = null,
        ?string $actionRoute = null,
        ?string $referenceId = null,
        ?int $userId = null
    ): SystemNotification {
        return SystemNotification::create([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'action_route' => $actionRoute,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);
    }
}

