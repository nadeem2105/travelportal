@extends('layouts.site')

@section('page')
<x-account.shell :accountNav="'profile'">
    <h1 class="font-display text-2xl font-bold">My Profile</h1>

    <form action="{{ route('account.profile.update') }}" method="POST" class="card mt-5 grid gap-4 p-6 sm:grid-cols-2">
        @csrf
        @method('PUT')
        <div>
            <label class="label">Full Name</label>
            <input type="text" name="name" class="input" value="{{ old('name', $user->name) }}" required>
        </div>
        <div>
            <label class="label">Email</label>
            <input type="email" name="email" class="input" value="{{ old('email', $user->email) }}" required>
        </div>
        <div>
            <label class="label">Phone</label>
            <input type="tel" name="phone" class="input" value="{{ old('phone', $user->phone) }}">
        </div>
        <div>
            <label class="label">Date of Birth</label>
            <input type="date" name="dob" class="input" value="{{ old('dob', $user->dob?->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="label">Gender</label>
            <select name="gender" class="input">
                <option value="">Prefer not to say</option>
                @foreach (['male', 'female', 'other'] as $g)
                    <option value="{{ $g }}" @selected(old('gender', $user->gender) === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">City</label>
            <input type="text" name="city" class="input" value="{{ old('city', $user->city) }}">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Address</label>
            <textarea name="address" rows="2" class="input">{{ old('address', $user->address) }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <button class="btn-primary btn-lg">Save Changes</button>
        </div>
    </form>
</x-account.shell>
@endsection
