<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Standard mobile-API JSON envelope: every response is
 *   { "success": bool, "message": string|null, "data": mixed, "errors": object? }
 *
 * Kept in one place so all v1 controllers answer in the same shape, which keeps
 * the mobile client's parsing trivial and consistent.
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function fail(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], fn ($v) => $v !== null), $status);
    }
}
