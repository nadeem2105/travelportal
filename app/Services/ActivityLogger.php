<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function log(
        string $action,
        ?string $module = null,
        ?string $description = null,
        array $properties = [],
        ?Request $request = null
    ): void {
        $request ??= request();

        ActivityLog::create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
        ]);
    }
}
