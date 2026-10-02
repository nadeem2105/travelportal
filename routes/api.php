<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Internal REST API (v1) — organized resource endpoints.
    // Authentication + rate limiting applied per route group.
    require __DIR__.'/api_v1.php';
});
