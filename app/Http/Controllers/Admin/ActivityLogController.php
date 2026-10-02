<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('admin')
            ->when($request->query('module'), fn ($q, $m) => $q->where('module', $m))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $modules = ActivityLog::distinct()->pluck('module')->filter();

        return view('admin.activity.index', compact('logs', 'modules'));
    }
}
