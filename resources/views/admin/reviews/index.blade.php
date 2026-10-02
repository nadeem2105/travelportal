@extends('layouts.admin')
@section('pageTitle', 'Reviews')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Customer Reviews</h1>
        <a href="{{ route('admin.reviews.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
    </div>

    <x-admin.filters
        :action="route('admin.reviews.index')"
        search-placeholder="Search title or content…"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']],
            ['name' => 'rating', 'label' => 'Rating', 'options' => ['5' => '5 ★', '4' => '4 ★', '3' => '3 ★', '2' => '2 ★', '1' => '1 ★'], 'all' => 'All Ratings'],
        ]"
        :count="$reviews->total()" />

    <div class="mt-4 space-y-3">
        @forelse ($reviews as $review)
            <div class="admin-card flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold">{{ $review->user?->name ?? 'Guest' }}</span>
                        <span class="text-amber-400 text-sm">{{ str_repeat('★', $review->rating) }}</span>
                        @if ($review->is_verified_booking) <span class="badge-soft !text-[10px]">✓ Verified Booking</span> @endif
                        <span class="text-xs text-ink-500">on {{ class_basename($review->reviewable_type) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-ink-700">{{ \Illuminate\Support\Str::limit($review->content, 140) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <form action="{{ route('admin.reviews.status', $review) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="status" value="approved">
                        <button class="btn-primary btn-sm" @disabled($review->status === 'approved')>Approve</button>
                    </form>
                    <form action="{{ route('admin.reviews.status', $review) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="status" value="rejected">
                        <button class="btn-ghost btn-sm !text-rose-600" @disabled($review->status === 'rejected')>Reject</button>
                    </form>
                    <form action="{{ route('admin.reviews.feature', $review) }}" method="POST" class="inline">
                        @csrf
                        <button class="text-xs font-bold {{ $review->is_featured ? 'text-amber-500' : 'text-ink-500' }}">★</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="admin-card text-center text-ink-500">No reviews yet</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $reviews->links() }}</div>
@endsection
