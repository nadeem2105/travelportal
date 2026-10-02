@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl pt-28">
    <h1 class="font-display text-3xl font-bold">{{ $page->title }}</h1>

    <div class="prose-page mt-6">
        {!! nl2br(e($page->content)) !!}
    </div>
</section>
@endsection
