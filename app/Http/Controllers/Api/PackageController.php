<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::with('destination')
            ->where('status', 'active')
            ->when($request->query('destination'), fn ($q, $d) => $q
                ->whereHas('destination', fn ($w) => $w->where('slug', $d)))
            ->paginate($request->integer('per_page', 15) ?: 15);

        return PackageResource::collection($packages);
    }

    public function show(Package $package)
    {
        abort_unless($package->status === 'active', 404);

        return new PackageResource($package->load(['destination', 'itineraries']));
    }
}
