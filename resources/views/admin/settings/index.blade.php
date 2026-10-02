@extends('layouts.admin')
@section('pageTitle', 'Settings')

@php($iconPaths = [
    'gear' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z',
    'briefcase' => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.275 23.275 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.195 2.195 0 0 1-.673-.38m0 0A5.998 5.998 0 0 1 6.75 10.5H4.5a2.25 2.25 0 0 1-2.25-2.25v-1.5A2.25 2.25 0 0 1 4.5 4.5h15a2.25 2.25 0 0 1 2.25 2.25v1.5a2.25 2.25 0 0 1-2.25 2.25h-2.25a6 6 0 0 1-3.75 5.25',
    'globe' => 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
    'palette' => 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
    'card' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
    'plug' => 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.65h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z',
    'percent' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
    'mail' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    'users' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z',
    'shield' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
    'chart' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
    'code' => 'M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5',
    'wrench' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z',
])

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold">Settings</h1>
            <p class="mt-0.5 text-sm text-ink-500">Manage your system configuration, business preferences and integrations</p>
        </div>
        <nav class="flex items-center gap-1 text-xs text-ink-500">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700">Dashboard</a>
            <span>›</span> <span class="text-ink-900">Settings</span>
            <span>›</span> <span class="font-semibold text-brand-700" x-data x-text="document.querySelector('.settings-rail button.ring-2, .settings-rail button.ring-brand-600') ? '' : 'General'">General</span>
        </nav>
    </div>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[300px_1fr]" x-data="settingsPage()">

        {{-- Category rail --}}
        <aside class="h-fit rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 lg:sticky lg:top-[70px]">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input type="text" x-model="query" placeholder="Search settings..." class="input !pl-10">
            </div>

            <nav class="settings-rail mt-3 space-y-1.5">
                @foreach ($categories as $category)
                    <button type="button"
                            @click="select('{{ $category['key'] }}')"
                            x-show="matches('{{ $category['label'] }} {{ $category['description'] }}')"
                            class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition"
                            :class="active === '{{ $category['key'] }}'
                                ? 'border-brand-600 bg-brand-600 text-white shadow-md'
                                : 'border-slate-200 bg-white hover:border-brand-300 hover:bg-brand-50/50'">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                              :class="active === '{{ $category['key'] }}' ? 'bg-white/20 text-white' : 'bg-{{ $category['color'] }}-50 text-{{ $category['color'] }}-600'">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$category['icon']] ?? $iconPaths['gear'] }}"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold">{{ $category['label'] }}</span>
                            <span class="block truncate text-xs" :class="active === '{{ $category['key'] }}' ? 'text-brand-100' : 'text-ink-500'">{{ $category['description'] }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- Panels --}}
        <div class="min-w-0">
            @foreach ($categories as $category)
                <div x-cloak x-show="active === '{{ $category['key'] }}'" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 sm:p-6"
                     x-data="{ tab: '{{ $category['tabs'][0]['key'] ?? '' }}' }">

                    {{-- Panel header --}}
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-bold">{{ $category['label'] }}</h2>
                            <p class="mt-0.5 text-sm text-ink-500">{{ $category['description'] }}</p>
                        </div>
                        <button type="submit" form="settings-form-{{ $category['key'] }}" class="btn-primary btn-md">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L22.5 12l-6-4.5v-3.75Zm-15 0v12.75L7.5 12l-6-4.5v-3.75ZM10.5 3.75v16.5l3-1.5v-13.5l-3-1.5Z"/></svg>
                            Save Changes
                        </button>
                    </div>

                    {{-- Tabs --}}
                    @if (count($category['tabs']) > 1)
                        <div class="mt-5 flex flex-wrap gap-1 rounded-xl bg-slate-50 p-1">
                            @foreach ($category['tabs'] as $tab)
                                <button type="button" @click="tab = '{{ $tab['key'] }}'"
                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                                        :class="tab === '{{ $tab['key'] }}' ? 'bg-white text-brand-700 shadow-sm ring-1 ring-slate-200' : 'text-ink-500 hover:text-ink-900'">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Category form --}}
                    <form id="settings-form-{{ $category['key'] }}" method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 space-y-5" @submit.prevent="$el.submit()">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $category['key'] }}">

                        @foreach ($category['tabs'] as $tab)
                            <div x-cloak x-show="tab === '{{ $tab['key'] }}'" class="space-y-5">

                                @foreach ($tab['sections'] as $section)
                                    <div class="rounded-2xl border border-slate-100 p-5">
                                        <h3 class="font-display text-base font-bold">{{ $section['title'] }}</h3>
                                        @if (! empty($section['description']))
                                            <p class="mt-0.5 text-xs text-ink-500">{{ $section['description'] }}</p>
                                        @endif

                                        @if (! empty($section['fields']))
                                            <div class="mt-4 grid gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                                                @foreach ($section['fields'] as $field)
                                                    @php($val = old('settings.' . $field['key'], $values[$field['key']] ?? ''))
                                                    <div class="{{ ($field['full'] ?? false) || in_array($field['type'], ['textarea']) ? 'sm:col-span-2 lg:col-span-3' : '' }}">
                                                        @if ($field['type'] === 'toggle')
                                                            <div x-data="{ on: @js($val === '1') }">
                                                                <input type="hidden" name="settings[{{ $field['key'] }}]" :value="on ? '1' : '0'">
                                                                <button type="button" class="flex items-center gap-3" @click="on = !on">
                                                                    <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                                                                          :class="on ? 'bg-brand-600' : 'bg-slate-300'">
                                                                        <span class="ml-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform duration-150"
                                                                              :class="on ? 'translate-x-[22px]' : 'translate-x-0'"></span>
                                                                    </span>
                                                                    <span class="text-sm font-semibold text-ink-900">
                                                                        {{ $field['label'] }}
                                                                        <span class="ml-1 text-xs font-medium" :class="on ? 'text-emerald-600' : 'text-ink-500'" x-text="on ? 'Enabled' : 'Disabled'"></span>
                                                                    </span>
                                                                </button>
                                                                @if (! empty($field['help']))
                                                                    <p class="mt-1 text-xs text-ink-500">{{ $field['help'] }}</p>
                                                                @endif
                                                            </div>
                                                        @elseif ($field['type'] === 'image')
                                                            <x-admin.image-upload name="settings[{{ $field['key'] }}]" :value="$val" :label="$field['label']" folder="settings" />
                                                            @if (! empty($field['help']))
                                                                <p class="mt-1 text-xs text-ink-500">{{ $field['help'] }}</p>
                                                            @endif
                                                        @else
                                                            <label class="label">
                                                                {{ $field['label'] }}
                                                                @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                                            </label>
                                                            @if ($field['type'] === 'select')
                                                                <select name="settings[{{ $field['key'] }}]" class="input">
                                                                    @foreach ($field['options'] as $optValue => $optLabel)
                                                                        <option value="{{ $optValue }}" @selected($val === $optValue)>{{ $optLabel }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif ($field['type'] === 'password')
                                                                <input type="password" name="settings[{{ $field['key'] }}]" class="input" value="" placeholder="Leave blank to keep saved secret" autocomplete="new-password">
                                                            @elseif ($field['type'] === 'textarea')
                                                                <textarea name="settings[{{ $field['key'] }}]" rows="3" class="input">{{ $val }}</textarea>
                                                                @if (! empty($field['help']))
                                                                    <p class="mt-1 text-xs text-ink-500">{{ $field['help'] }}</p>
                                                                @endif
                                                            @else
                                                                <input type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : 'text') }}"
                                                                       step="any" name="settings[{{ $field['key'] }}]" class="input"
                                                                       value="{{ $val }}" @if(!empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if (! empty($section['links']))
                                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                                @foreach ($section['links'] as $link)
                                                    <a href="{{ route($link['route']) }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 p-3.5 transition hover:border-brand-300 hover:bg-brand-50/50">
                                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                                        </span>
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-bold text-ink-900">{{ $link['label'] }}</span>
                                                            <span class="block truncate text-xs text-ink-500">{{ $link['description'] }}</span>
                                                        </span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('settingsPage', () => ({
            active: 'general',
            query: '',
            select(key) {
                this.active = key;
                this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            },
            matches(text) {
                if (!this.query) return true;
                return text.toLowerCase().includes(this.query.toLowerCase());
            },
        }));
    });
</script>
@endpush
