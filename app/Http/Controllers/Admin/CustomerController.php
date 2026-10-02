<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = ['name', 'email', 'created_at', 'bookings_count'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $customers = User::withCount('bookings')
            ->when(trim((string) $request->query('q')), fn ($query, $q) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', (int) $request->input('is_active')))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'most-bookings', fn ($q) => $q->orderByDesc('bookings_count'), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function export(Request $request): StreamedResponse
    {
        $sortable = ['name', 'email', 'created_at', 'bookings_count'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'customers-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'customers', 'Exported customers CSV');

        return response()->streamDownload(function () use ($request, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Bookings', 'Active', 'Joined']);

            User::withCount('bookings')
                ->when(trim((string) $request->query('q')), fn ($query, $q) => $query->where(fn ($w) => $w
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")))
                ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', (int) $request->input('is_active')))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'most-bookings', fn ($q) => $q->orderByDesc('bookings_count'), fn ($q) => $q->latest()))
                ->chunk(500, function ($customers) use ($out) {
                    foreach ($customers as $customer) {
                        fputcsv($out, [
                            $customer->id,
                            $customer->name,
                            $customer->email,
                            $customer->phone ?? '',
                            $customer->bookings_count,
                            $customer->is_active ? 'Yes' : 'No',
                            $customer->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show(User $user)
    {
        $user->loadCount('bookings')->load(['bookings' => fn ($q) => $q->limit(15)]);

        return view('admin.customers.show', compact('user'));
    }

    public function toggle(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        ActivityLogger::log('update', 'customers', ($user->is_active ? 'Activated' : 'Deactivated') . " customer {$user->email}");

        return back()->with('success', 'Customer ' . ($user->is_active ? 'activated' : 'deactivated') . '.');
    }
}
