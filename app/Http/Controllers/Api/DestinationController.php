<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        return \App\Http\Resources\DestinationResource::collection(
            Destination::where('status', 'active')
                ->orderBy('sort_order')
                ->paginate($request->integer('per_page', 20) ?: 20)
        );
    }

    public function show(Destination $destination)
    {
        abort_unless($destination->status === 'active', 404);

        return new \App\Http\Resources\DestinationResource($destination);
    }
}
