@extends('layouts.admin')
@section('pageTitle', 'Drivers')

@section('content')
<div x-data="driverDir()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-bold">Drivers</h1>
            <p class="text-xs text-ink-500">Reusable driver &amp; vehicle profiles for trip assignments</p>
        </div>
        <button type="button" @click="openAdd()" class="btn-primary btn-sm">+ Add Driver</button>
    </div>

    <x-admin.filters
        :action="route('admin.trip-ops.drivers.index')"
        search-placeholder="Search name, phone, vehicle, vendor…"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'all' => 'All statuses', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        ]"
        :count="$drivers->total()"
    />

    {{-- Desktop table --}}
    <div class="admin-card mt-4 hidden overflow-x-auto md:block">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Vehicle</th>
                    <th>Vendor</th>
                    <th class="text-center">Active jobs</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($drivers as $driver)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink-800">{{ $driver->name }}</div>
                            @if ($driver->rating)<div class="text-xs text-amber-600">★ {{ $driver->rating }}</div>@endif
                        </td>
                        <td class="text-xs">
                            {{ $driver->phone }}
                            @if ($driver->alt_phone)<div class="text-ink-400">{{ $driver->alt_phone }}</div>@endif
                        </td>
                        <td class="text-xs">{{ $driver->vehicleLabel() }}</td>
                        <td class="text-xs">{{ $driver->vendor_name ?: '—' }}</td>
                        <td class="text-center font-semibold">{{ $driver->active_assignments_count }}</td>
                        <td>
                            <span class="status-pill {{ $driver->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $driver->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-1">
                                <button type="button" @click="openEdit({{ Illuminate\Support\Js::from($driver) }})" class="btn-ghost btn-sm">Edit</button>
                                <form action="{{ route('admin.trip-ops.drivers.toggle', $driver) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="btn-ghost btn-sm">{{ $driver->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                <form action="{{ route('admin.trip-ops.drivers.destroy', $driver) }}" method="POST" class="inline" onsubmit="return confirm('Delete this driver?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm !text-rose-600">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-500">No drivers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="mt-4 space-y-3 md:hidden">
        @forelse ($drivers as $driver)
            <div class="admin-card !p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink-800">{{ $driver->name }}</p>
                        <p class="text-xs text-ink-500">{{ $driver->phone }}</p>
                    </div>
                    <span class="status-pill shrink-0 {{ $driver->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $driver->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <p class="mt-1 text-xs text-ink-500">{{ $driver->vehicleLabel() }}@if ($driver->vendor_name) · {{ $driver->vendor_name }}@endif</p>
                <p class="mt-1 text-xs text-ink-500">{{ $driver->active_assignments_count }} active job{{ $driver->active_assignments_count === 1 ? '' : 's' }}</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    <button type="button" @click="openEdit({{ Illuminate\Support\Js::from($driver) }})" class="btn-ghost btn-sm">Edit</button>
                    <form action="{{ route('admin.trip-ops.drivers.toggle', $driver) }}" method="POST" class="inline">
                        @csrf
                        <button class="btn-ghost btn-sm">{{ $driver->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                    <form action="{{ route('admin.trip-ops.drivers.destroy', $driver) }}" method="POST" class="inline" onsubmit="return confirm('Delete this driver?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost btn-sm !text-rose-600">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="admin-card py-6 text-center text-sm text-ink-500">No drivers found.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $drivers->links() }}</div>

    {{-- Add / Edit modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8" @keydown.escape.window="open = false">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl" @click.outside="open = false">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="font-display text-lg font-bold" x-text="mode === 'edit' ? 'Edit Driver' : 'Add Driver'"></h2>
                <button type="button" @click="open = false" class="text-ink-400 hover:text-ink-700">&times;</button>
            </div>
            <form :action="formAction" method="POST" class="px-6 py-5">
                @csrf
                <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Name *</label>
                        <input type="text" name="name" x-model="form.name" required class="input" placeholder="Driver full name">
                    </div>
                    <div>
                        <label class="label">Phone *</label>
                        <input type="text" name="phone" x-model="form.phone" required class="input" placeholder="+91 …">
                    </div>
                    <div>
                        <label class="label">Alternate Phone</label>
                        <input type="text" name="alt_phone" x-model="form.alt_phone" class="input">
                    </div>
                    <div>
                        <label class="label">License Number</label>
                        <input type="text" name="license_number" x-model="form.license_number" class="input">
                    </div>
                    <div>
                        <label class="label">Vehicle Number</label>
                        <input type="text" name="vehicle_number" x-model="form.vehicle_number" class="input" placeholder="JK01AB1234">
                    </div>
                    <div>
                        <label class="label">Vehicle Type</label>
                        <input type="text" name="vehicle_type" x-model="form.vehicle_type" class="input" placeholder="sedan / suv / tempo">
                    </div>
                    <div>
                        <label class="label">Vehicle Model</label>
                        <input type="text" name="vehicle_model" x-model="form.vehicle_model" class="input" placeholder="Toyota Innova">
                    </div>
                    <div>
                        <label class="label">Vendor Name</label>
                        <input type="text" name="vendor_name" x-model="form.vendor_name" class="input">
                    </div>
                    <div>
                        <label class="label">Home Base</label>
                        <input type="text" name="home_base" x-model="form.home_base" class="input" placeholder="Srinagar">
                    </div>
                    <div>
                        <label class="label">Rating (0–5)</label>
                        <input type="number" step="0.1" min="0" max="5" name="rating" x-model="form.rating" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Notes</label>
                        <textarea name="notes" rows="2" x-model="form.notes" class="input"></textarea>
                    </div>
                    <div class="flex items-center pt-1">
                        <input type="hidden" name="is_active" value="0">
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded"> Active</label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="open = false" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Save Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function driverDir() {
        return {
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'add',
            storeUrl: '{{ route('admin.trip-ops.drivers.store') }}',
            updateBase: '{{ url('admin/trip-ops/drivers') }}',
            formAction: '{{ route('admin.trip-ops.drivers.store') }}',
            form: this.blank(),
            blank() {
                return { id: null, name: '', phone: '', alt_phone: '', license_number: '', vehicle_number: '', vehicle_type: '', vehicle_model: '', vendor_name: '', home_base: '', rating: '', notes: '', is_active: true };
            },
            openAdd() {
                this.mode = 'add';
                this.form = this.blank();
                this.formAction = this.storeUrl;
                this.open = true;
            },
            openEdit(driver) {
                this.mode = 'edit';
                this.form = {
                    id: driver.id, name: driver.name ?? '', phone: driver.phone ?? '', alt_phone: driver.alt_phone ?? '',
                    license_number: driver.license_number ?? '', vehicle_number: driver.vehicle_number ?? '',
                    vehicle_type: driver.vehicle_type ?? '', vehicle_model: driver.vehicle_model ?? '',
                    vendor_name: driver.vendor_name ?? '', home_base: driver.home_base ?? '',
                    rating: driver.rating ?? '', notes: driver.notes ?? '', is_active: !!driver.is_active,
                };
                this.formAction = this.updateBase + '/' + driver.id;
                this.open = true;
            },
        };
    }
</script>
@endsection
