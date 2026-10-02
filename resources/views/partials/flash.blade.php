{{-- Flash messages --}}
@if (session('success') || session('error') || $errors->any())
    <div class="shell mt-4 space-y-3">
        @if (session('success'))
            <div class="alert-success" x-data x-init="setTimeout(() => $el.remove(), 6000)">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif
        @if ($errors->any() && ! session('error'))
            <div class="alert-error">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
