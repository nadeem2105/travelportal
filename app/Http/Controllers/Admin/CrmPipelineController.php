<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;

class CrmPipelineController extends Controller
{
    public function index()
    {
        $pipelines = Pipeline::with(['stages' => fn ($q) => $q->withCount('leads')])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.crm.pipelines.index', compact('pipelines'));
    }
}
