@extends('layouts.site')

@section('page')
<x-account.shell :accountNav="'travellers'">
    <h1 class="font-display text-2xl font-bold">Saved Travellers</h1>
    <p class="text-sm text-ink-500">Save traveller details for faster checkout.</p>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        @forelse ($travellers as $traveller)
            <div class="card flex items-center justify-between p-5">
                <div>
                    <p class="font-bold text-ink-900">{{ $traveller->title ? $traveller->title . '. ' : '' }}{{ $traveller->full_name }}</p>
                    <p class="text-xs text-ink-500">{{ $traveller->gender ? ucfirst($traveller->gender) . ' · ' : '' }}{{ optional($traveller->dob)->format('d M Y') }}</p>
                </div>
                <form action="{{ route('account.travellers.destroy', $traveller) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm font-semibold text-rose-500 hover:text-rose-700">Remove</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-ink-500">No saved travellers yet.</p>
        @endforelse
    </div>

    <form action="{{ route('account.travellers.store') }}" method="POST" class="card mt-6 grid gap-4 p-6 sm:grid-cols-3">
        @csrf
        <div>
            <label class="label">Title</label>
            <select name="title" class="input">
                <option value="">—</option>
                @foreach (['Mr', 'Mrs', 'Ms'] as $t) <option>{{ $t }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">First Name</label>
            <input type="text" name="first_name" class="input" required>
        </div>
        <div>
            <label class="label">Last Name</label>
            <input type="text" name="last_name" class="input">
        </div>
        <div>
            <label class="label">Date of Birth</label>
            <input type="date" name="dob" class="input">
        </div>
        <div>
            <label class="label">Gender</label>
            <select name="gender" class="input">
                <option value="">—</option>
                @foreach (['male', 'female', 'other'] as $g) <option value="{{ $g }}">{{ ucfirst($g) }}</option> @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button class="btn-primary btn-md w-full">Add Traveller</button>
        </div>
    </form>
</x-account.shell>
@endsection
