{{-- Generic admin form: $entry, $spec, $viewKey --}}
@extends('layouts.admin')
@section('pageTitle', ($entry->exists ? 'Edit ' : 'New ') . $spec['label'])

@section('content')
    <h1 class="font-display text-xl font-bold">{{ $entry->exists ? 'Edit' : 'New' }} {{ $spec['label'] }}</h1>

    <form action="{{ $entry->exists ? route('admin.' . $viewKey . '.update', $entry) : route('admin.' . $viewKey . '.store') }}"
          method="POST" enctype="multipart/form-data" class="admin-card mt-4 max-w-3xl">
        @csrf
        @if ($entry->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($spec['fields'] as $name => $field)
                @php($value = old($name, $entry->{$name} ?? ($field['default'] ?? '')))
                <div class="{{ in_array($field['type'], ['textarea']) ? 'sm:col-span-2' : '' }}">
                    <label class="label">{{ $field['label'] }}</label>

                    @if ($field['type'] === 'media')
                        <x-admin.image-upload :name="$name" :value="is_array($value) ? null : $value" :label="$field['label']" />
                    @elseif ($field['type'] === 'textarea')
                        <textarea name="{{ $name }}" rows="{{ $field['rows'] ?? 4 }}" class="input">{{ is_array($value) ? implode("\n", $value) : $value }}</textarea>
                    @elseif ($field['type'] === 'checkbox')
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                            <input type="checkbox" name="{{ $name }}" value="1" class="accent-brand-600" @checked($entry->{$name} ?? false)>
                            {{ $field['label'] }}
                        </label>
                    @elseif ($field['type'] === 'multi')
                        <div class="flex flex-wrap gap-3 pt-1">
                            @foreach ($field['options'] as $optValue => $optLabel)
                                <label class="flex cursor-pointer items-center gap-1.5 text-sm text-ink-700">
                                    <input type="checkbox" name="{{ $name }}[]" value="{{ $optValue }}" class="accent-brand-600"
                                           @checked(is_array($value) && in_array($optValue, $value))>
                                    {{ $optLabel }}
                                </label>
                            @endforeach
                        </div>
                    @elseif ($field['type'] === 'select')
                        <select name="{{ $name }}" class="input">
                            @if (! empty($field['nullable'])) <option value="">— None —</option> @endif
                            @if (! empty($field['options']))
                                @foreach ($field['options'] as $optValue => $optLabel)
                                    <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                                @endforeach
                            @elseif (! empty($field['options_from']))
                                @foreach ($field['options_from']::orderBy('name')->get() as $option)
                                    <option value="{{ $option->id }}" @selected((string) $value === (string) $option->id)>{{ $option->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    @elseif ($field['type'] === 'date')
                        <input type="date" name="{{ $name }}" class="input" value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '' }}">
                    @elseif ($field['type'] === 'number')
                        <input type="number" step="any" name="{{ $name }}" class="input" value="{{ $value }}">
                    @else
                        <input type="{{ $field['type'] === 'email' ? 'email' : 'text' }}" name="{{ $name }}" class="input" value="{{ is_array($value) ? implode(', ', $value) : $value }}">
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex gap-3">
            <button class="btn-primary btn-md">{{ $entry->exists ? 'Save Changes' : 'Create' }}</button>
            <a href="{{ route('admin.' . $viewKey . '.index') }}" class="btn-ghost btn-md">Cancel</a>
        </div>
    </form>
@endsection
