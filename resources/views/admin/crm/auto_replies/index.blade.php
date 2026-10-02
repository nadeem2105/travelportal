@extends('layouts.admin')
@section('pageTitle', 'WhatsApp Auto-replies')

@section('content')
<div x-data="autoReplyManager()" x-init="init()" class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-ink-500 mb-1">
                <a href="{{ route('admin.crm.index') }}" class="hover:text-brand-600 transition">CRM</a>
                <span>›</span>
                <a href="{{ route('admin.whatsapp.index') }}" class="hover:text-brand-600 transition">WhatsApp Inbox</a>
                <span>›</span>
                <span class="font-semibold text-ink-700">Auto-replies & Chatbot</span>
            </div>
            <h1 class="font-display text-2xl font-bold text-ink-900">WhatsApp Auto-replies & Interactive Bot</h1>
            <p class="text-xs text-ink-500 mt-0.5">Automated replies with headers, footers, interactive quick-reply buttons, and Meta templates</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form action="{{ route('admin.whatsapp-templates.sync') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn-secondary btn-sm flex items-center gap-1.5" title="Fetch latest approved templates from Meta WhatsApp Cloud API">
                    <svg class="h-4 w-4 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span>Sync Meta Templates</span>
                </button>
            </form>
            <a href="{{ route('admin.whatsapp-templates.index') }}" class="btn-ghost btn-sm flex items-center gap-1.5">
                <svg class="h-4 w-4 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span>Templates ({{ $templates->count() }})</span>
            </a>
            <button type="button" class="btn-primary btn-sm flex items-center gap-1.5 shadow-sm" @click="openNew()">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>New Auto-reply Rule</span>
            </button>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
            <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 flex items-center gap-2">
            <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold mb-1 flex items-center gap-1.5">
                <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                <span>Please fix the following issues:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5 text-xs">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="admin-card p-4">
            <div class="text-xs font-semibold text-ink-500 uppercase tracking-wider">Total Rules</div>
            <div class="mt-1 flex items-baseline justify-between">
                <div class="text-2xl font-bold text-ink-900">{{ $rules->count() }}</div>
                <span class="text-xs text-ink-400">rules</span>
            </div>
        </div>
        <div class="admin-card p-4">
            <div class="text-xs font-semibold text-ink-500 uppercase tracking-wider">Active Rules</div>
            <div class="mt-1 flex items-baseline justify-between">
                <div class="text-2xl font-bold text-emerald-600">{{ $rules->where('active', true)->count() }}</div>
                <span class="text-xs text-emerald-600/80">live</span>
            </div>
        </div>
        <div class="admin-card p-4">
            <div class="text-xs font-semibold text-ink-500 uppercase tracking-wider">Interactive Buttons</div>
            <div class="mt-1 flex items-baseline justify-between">
                @php $interactiveCount = $rules->filter(fn($r) => !empty($r->buttons) && count(array_filter($r->buttons)) > 0)->count(); @endphp
                <div class="text-2xl font-bold text-teal-600">{{ $interactiveCount }}</div>
                <span class="text-xs text-teal-600/80">with buttons</span>
            </div>
        </div>
        <div class="admin-card p-4">
            <div class="text-xs font-semibold text-ink-500 uppercase tracking-wider">Default Fallback</div>
            <div class="mt-1 flex items-baseline justify-between">
                @php $hasDefault = $rules->where('is_default', true)->where('active', true)->first(); @endphp
                <div class="text-sm font-bold {{ $hasDefault ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ $hasDefault ? '✓ Configured' : '⚠ None set' }}
                </div>
                <span class="text-xs text-ink-400">catch-all</span>
            </div>
        </div>
    </div>

    {{-- Rules Table --}}
    <div class="admin-card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/75 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="font-bold text-sm text-ink-800">Auto-reply Rules Order</h3>
                <span class="text-xs text-ink-400">(evaluated top-to-bottom by priority)</span>
            </div>
            <span class="text-xs text-ink-500">Lower priority number runs first</span>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="w-16 text-center">Priority</th>
                        <th class="min-w-[160px]">Rule Name</th>
                        <th class="min-w-[220px]">Trigger Condition</th>
                        <th class="min-w-[280px]">Bot Response & Interactive Features</th>
                        <th class="text-center">Settings</th>
                        <th class="text-right w-36">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rules as $rule)
                        @php
                            $ruleData = [
                                'id' => $rule->id,
                                'name' => $rule->name,
                                'match_type' => $rule->match_type,
                                'keywords' => $rule->keywords ?? [],
                                'keywords_raw' => implode(', ', $rule->keywords ?? []),
                                'reply_type' => $rule->reply_type,
                                'reply_text' => $rule->reply_text ?? '',
                                'header_type' => $rule->header_type ?? 'text',
                                'header_image_url' => $rule->header_image_url ?? '',
                                'header_text' => $rule->header_text ?? '',
                                'footer_text' => $rule->footer_text ?? '',
                                'buttons' => is_array($rule->buttons) ? array_values(array_filter($rule->buttons)) : [],
                                'template_name' => $rule->template_name ?? '',
                                'template_language' => $rule->template_language ?? 'en_US',
                                'template_params' => array_values($rule->template_params ?? []),
                                'template_header_media_url' => $rule->template_header_media_url ?? '',
                                'is_default' => (bool) $rule->is_default,
                                'is_handoff' => (bool) $rule->is_handoff,
                                'active' => (bool) $rule->active,
                                'priority' => (int) $rule->priority,
                            ];
                        @endphp
                        <tr class="{{ $rule->active ? 'hover:bg-slate-50/50' : 'opacity-60 bg-slate-50/25' }} transition">
                            <td class="text-center font-mono text-xs font-bold text-ink-600">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-700">
                                    {{ $rule->priority }}
                                </span>
                            </td>
                            <td>
                                <div class="font-semibold text-ink-900 text-sm">{{ $rule->name }}</div>
                                <div class="text-[11px] text-ink-400 mt-0.5">Created {{ $rule->created_at?->format('d M Y') }}</div>
                            </td>
                            <td>
                                @if ($rule->is_default)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-0.5 text-xs font-semibold text-purple-700 border border-purple-200">
                                        <svg class="h-3 w-3 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                                        Default Fallback (Any text)
                                    </span>
                                @else
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-700">
                                            {{ str_replace('_', ' ', $rule->match_type) }}
                                        </span>
                                        <span class="text-[11px] text-ink-400">({{ count($rule->keywords ?? []) }} keywords)</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1 max-w-sm">
                                        @foreach (array_slice($rule->keywords ?? [], 0, 6) as $kw)
                                            <span class="inline-flex items-center rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 border border-sky-100">
                                                {{ $kw }}
                                            </span>
                                        @endforeach
                                        @if (count($rule->keywords ?? []) > 6)
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">
                                                +{{ count($rule->keywords ?? []) - 6 }} more
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($rule->reply_type === 'template')
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 border border-teal-200">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                            <span class="font-mono">{{ $rule->template_name }}</span>
                                        </span>
                                        <span class="text-[10px] text-ink-400 uppercase font-bold tracking-wider">({{ $rule->template_language }})</span>
                                    </div>
                                    @php $matchedTpl = $templates->firstWhere('name', $rule->template_name); @endphp
                                    @if (!empty($matchedTpl['body_preview']))
                                        <p class="text-xs text-ink-500 mt-1 line-clamp-2 italic">"{{ \Illuminate\Support\Str::limit($matchedTpl['body_preview'], 90) }}"</p>
                                    @endif
                                    @if (!empty($rule->template_params))
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($rule->template_params as $pi => $pv)
                                                <span class="inline-flex items-center gap-1 rounded bg-slate-100 border border-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                                    <span class="font-mono text-slate-400">#{{ $pi + 1 }}</span>
                                                    <span class="truncate max-w-[90px]">{{ $pv }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if (!empty($rule->template_header_media_url))
                                        <div class="mt-1 text-[10px] text-ink-400 flex items-center gap-1">
                                            <span class="uppercase font-semibold tracking-wider text-emerald-600">Header media:</span>
                                            <a href="{{ $rule->template_header_media_url }}" target="_blank" class="text-brand-600 hover:underline truncate max-w-[160px]">{{ basename(parse_url($rule->template_header_media_url, PHP_URL_PATH)) ?: 'View file' }}</a>
                                        </div>
                                    @endif
                                @else
                                    <div class="space-y-1.5 max-w-md">
                                        @if ($rule->header_type === 'image' && $rule->header_image_url)
                                            <div class="text-[11px] font-bold text-ink-900 flex items-center gap-1.5">
                                                <span class="text-[10px] uppercase tracking-wider text-emerald-600 font-semibold">Image Header:</span>
                                                <a href="{{ $rule->header_image_url }}" target="_blank" class="inline-flex items-center gap-1 text-brand-600 hover:underline">
                                                    <img src="{{ $rule->header_image_url }}" alt="Header" class="h-6 w-10 object-cover rounded border border-slate-200">
                                                    <span class="text-[10px] truncate max-w-[120px]">{{ basename(parse_url($rule->header_image_url, PHP_URL_PATH)) ?: 'View image' }}</span>
                                                </a>
                                            </div>
                                        @elseif ($rule->header_text)
                                            <div class="text-[11px] font-bold text-ink-900 flex items-center gap-1">
                                                <span class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold">Header:</span>
                                                <span>{{ $rule->header_text }}</span>
                                            </div>
                                        @endif

                                        <div class="text-xs text-ink-800 bg-slate-50 border border-slate-200/70 rounded-lg p-2">
                                            <div class="line-clamp-2 whitespace-pre-line">{{ $rule->reply_text }}</div>
                                        </div>

                                        @if (!empty($rule->buttons) && is_array($rule->buttons))
                                            <div class="flex flex-wrap items-center gap-1 pt-0.5">
                                                <span class="text-[10px] uppercase font-bold tracking-wider text-teal-700 mr-0.5">Buttons:</span>
                                                @foreach (array_filter($rule->buttons) as $btn)
                                                    <span class="inline-flex items-center gap-1 rounded border border-teal-300 bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-800">
                                                        <span>🔘 {{ is_array($btn) ? ($btn['title'] ?? '') : $btn }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($rule->footer_text)
                                            <div class="text-[11px] text-ink-500 italic">
                                                <span class="not-italic text-[10px] uppercase font-semibold text-ink-400">Footer:</span> {{ $rule->footer_text }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="flex flex-col items-center gap-1">
                                    @if ($rule->active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                                            Disabled
                                        </span>
                                    @endif

                                    @if ($rule->is_handoff)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">
                                            👨‍💼 Agent Handoff
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" class="btn-ghost btn-xs text-ink-700 hover:text-brand-600"
                                        @click="openEdit(@js($ruleData))" title="Edit this rule">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.whatsapp-auto-replies.toggle', $rule) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn-ghost btn-xs text-ink-600" title="{{ $rule->active ? 'Disable' : 'Enable' }}">
                                            {{ $rule->active ? 'Pause' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.whatsapp-auto-replies.destroy', $rule) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this auto-reply rule?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-xs text-rose-600 hover:bg-rose-50" title="Delete">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600 mb-3">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.154-.63 4.88 4.88 0 0 0 .866-2.552C3.12 16.39 2 14.333 2 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-ink-800">No WhatsApp Auto-reply Rules Yet</h3>
                                <p class="text-xs text-ink-500 max-w-sm mx-auto mt-1">Create your first rule with custom greetings, headers, footers, interactive buttons, or templates.</p>
                                <button type="button" class="btn-primary btn-sm mt-4 inline-flex items-center gap-1.5" @click="openNew()">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    <span>Create First Auto-reply Rule</span>
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create / Edit Modal (Advanced with Header, Footer, Buttons & Live Preview) --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-3 sm:p-5 lg:p-6"
         @keydown.escape.window="open = false">
        <div class="w-full max-w-4xl rounded-2xl bg-white shadow-2xl overflow-hidden my-auto" @click.outside="open = false">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <div>
                    <h2 class="font-display text-lg font-bold text-ink-900" x-text="editing ? 'Edit WhatsApp Auto-reply Rule' : 'New Advanced WhatsApp Auto-reply Rule'"></h2>
                    <p class="text-xs text-ink-500">Configure triggers, headers, footers, quick-reply buttons, and Meta templates</p>
                </div>
                <button type="button" @click="open = false" class="rounded-lg p-1.5 text-ink-400 hover:bg-slate-200/60 hover:text-ink-700 transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal Form (Two Columns: Config on left, Live WhatsApp Phone Preview on right) --}}
            <form :action="editing ? '{{ url('admin/whatsapp-auto-replies') }}/' + editing.id : '{{ route('admin.whatsapp-auto-replies.store') }}'"
                  method="POST" enctype="multipart/form-data" class="p-6 max-h-[84vh] overflow-y-auto">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 items-start">

                    {{-- Left Column: Settings --}}
                    <div class="space-y-5">

                        {{-- Rule Name --}}
                        <div>
                            <label class="label">Rule Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" x-model="form.name" required class="input"
                                   placeholder="e.g. Main Welcome Menu, Tour Packages Query, Cab Booking Quick Reply">
                        </div>

                        {{-- SECTION 1: TRIGGER --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3.5">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-bold">1</span>
                                <h3 class="font-bold text-sm text-ink-900">Trigger: Which Incoming Message Triggers This?</h3>
                            </div>

                            {{-- Trigger Type Choice --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <label class="flex items-start gap-2.5 rounded-lg border p-2.5 cursor-pointer transition text-xs"
                                       :class="triggerType === 'keyword' ? 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="_trigger_type" value="keyword" class="mt-0.5 text-brand-600 focus:ring-brand-500"
                                           :checked="triggerType === 'keyword'" @change="setTriggerType('keyword')">
                                    <div>
                                        <span class="block font-semibold text-ink-900">Keyword Match</span>
                                        <span class="block text-[11px] text-ink-500">Customer message matches specific words/phrases</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-2.5 rounded-lg border p-2.5 cursor-pointer transition text-xs"
                                       :class="triggerType === 'default' ? 'border-purple-500 bg-purple-50/50 ring-2 ring-purple-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="_trigger_type" value="default" class="mt-0.5 text-purple-600 focus:ring-purple-500"
                                           :checked="triggerType === 'default'" @change="setTriggerType('default')">
                                    <div>
                                        <span class="block font-semibold text-ink-900">Default Fallback</span>
                                        <span class="block text-[11px] text-ink-500">Triggers on ANY message if no keyword matches</span>
                                    </div>
                                </label>
                            </div>

                            <input type="hidden" name="is_default" :value="form.is_default ? '1' : '0'">

                            {{-- Keyword Input --}}
                            <div x-show="triggerType === 'keyword'" x-transition class="space-y-2.5 pt-1">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="label text-xs">Match Condition</label>
                                        <select name="match_type" x-model="form.match_type" class="input !text-xs">
                                            <option value="contains">Contains Keyword (Anywhere in text)</option>
                                            <option value="exact">Exact Match (Message equals keyword)</option>
                                            <option value="starts_with">Starts With (Message begins with keyword)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label text-xs">Priority</label>
                                        <input type="number" name="priority" x-model="form.priority" class="input !text-xs" min="0" max="9999" placeholder="100">
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="label text-xs !mb-0">Trigger Keywords / Phrases <span class="text-rose-500">*</span></label>
                                        <span class="text-[10px] text-ink-400">Comma or line separated</span>
                                    </div>
                                    <textarea name="keywords_raw" x-model="form.keywords_raw" rows="2" class="input text-xs"
                                              placeholder="e.g. hi, hello, start, menu, packages, price, cab"></textarea>

                                    {{-- Keyword Chips --}}
                                    <div class="mt-1.5 flex flex-wrap gap-1" x-show="parsedKeywords.length > 0">
                                        <template x-for="(kw, idx) in parsedKeywords" :key="idx">
                                            <span class="inline-flex items-center gap-1 rounded bg-brand-50 border border-brand-200 px-1.5 py-0.5 text-[11px] font-semibold text-brand-700">
                                                <span x-text="kw"></span>
                                            </span>
                                        </template>
                                    </div>

                                    {{-- Quick Add Suggestions --}}
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="addKeywordTag('hi'); addKeywordTag('hello'); addKeywordTag('start');">
                                            + Hi/Hello
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="addKeywordTag('package'); addKeywordTag('packages'); addKeywordTag('tour');">
                                            + Packages
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="addKeywordTag('cab'); addKeywordTag('taxi'); addKeywordTag('driver');">
                                            + Cabs
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="addKeywordTag('hotel'); addKeywordTag('stay'); addKeywordTag('houseboat');">
                                            + Hotels
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="addKeywordTag('price'); addKeywordTag('cost'); addKeywordTag('quote');">
                                            + Price
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="addKeywordTag('agent'); addKeywordTag('human'); addKeywordTag('help');">
                                            + Agent
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Fallback Notice --}}
                            <div x-show="triggerType === 'default'" x-transition class="rounded-lg bg-purple-50 p-2.5 text-xs text-purple-800 space-y-0.5">
                                <div class="font-bold flex items-center gap-1 text-purple-900">
                                    <svg class="h-3.5 w-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                                    <span>Universal Fallback Rule</span>
                                </div>
                                <p class="text-[11px]">Triggers on ANY customer message when no keyword matches. Perfect for a general welcome or main navigation menu.</p>
                            </div>
                        </div>

                        {{-- SECTION 2: RESPONSE MESSAGE --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-teal-600 text-white text-xs font-bold">2</span>
                                    <h3 class="font-bold text-sm text-ink-900">Response: What Message Should Be Sent?</h3>
                                </div>
                            </div>

                            {{-- Response Mode Toggle --}}
                            <div class="grid grid-cols-2 gap-2.5">
                                <label class="flex items-start gap-2 rounded-lg border p-2.5 cursor-pointer transition text-xs"
                                       :class="replyType !== 'template' ? 'border-teal-500 bg-teal-50/50 ring-2 ring-teal-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="reply_type" value="text" class="mt-0.5 text-teal-600 focus:ring-teal-500"
                                           :checked="replyType !== 'template'" @change="setReplyType('text')">
                                    <div>
                                        <span class="block font-semibold text-ink-900">Custom Message & Buttons</span>
                                        <span class="block text-[11px] text-ink-500">Header, Body, Footer & Quick Reply Buttons</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-2 rounded-lg border p-2.5 cursor-pointer transition text-xs"
                                       :class="replyType === 'template' ? 'border-teal-500 bg-teal-50/50 ring-2 ring-teal-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="reply_type" value="template" class="mt-0.5 text-teal-600 focus:ring-teal-500"
                                           :checked="replyType === 'template'" @change="setReplyType('template')">
                                    <div>
                                        <span class="block font-semibold text-ink-900">Meta Template</span>
                                        <span class="block text-[11px] text-ink-500">Official approved WhatsApp template</span>
                                    </div>
                                </label>
                            </div>

                            {{-- CUSTOM MESSAGE BUILDER (HEADER + BODY + FOOTER + BUTTONS) --}}
                            <div x-show="replyType !== 'template'" x-transition class="space-y-3.5 pt-1">

                                {{-- HEADER BUILDER (None vs Text vs Image) --}}
                                <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="label text-xs !mb-0 font-bold text-ink-900">Header Option (Optional)</label>
                                            <p class="text-[10px] text-ink-500">Choose between no header, a bold text headline, or a full-width photo banner.</p>
                                        </div>
                                        <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50 text-xs">
                                            <button type="button" class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition"
                                                    :class="form.header_type === 'none' ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
                                                    @click="setHeaderType('none')">
                                                None
                                            </button>
                                            <button type="button" class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition"
                                                    :class="form.header_type === 'text' ? 'bg-white text-teal-700 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
                                                    @click="setHeaderType('text')">
                                                ✍️ Text Header
                                            </button>
                                            <button type="button" class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition"
                                                    :class="form.header_type === 'image' ? 'bg-white text-emerald-700 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
                                                    @click="setHeaderType('image')">
                                                🖼️ Image Header
                                            </button>
                                        </div>
                                    </div>

                                    <input type="hidden" name="header_type" :value="form.header_type">

                                    {{-- Text Header Input --}}
                                    <div x-show="form.header_type === 'text'" x-transition class="space-y-1 pt-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-medium text-ink-700">Headline Text (Max 60 chars)</span>
                                            <span class="text-[10px] text-ink-400" x-text="(form.header_text ? form.header_text.length : 0) + '/60'"></span>
                                        </div>
                                        <input type="text" name="header_text" x-model="form.header_text" maxlength="60" class="input !text-xs font-semibold"
                                               placeholder="e.g. 🏔️ TravelQue Cashmir | Holiday Packages">
                                    </div>

                                    {{-- Image Header Input & Presets --}}
                                    <div x-show="form.header_type === 'image'" x-transition class="space-y-2.5 pt-1">
                                        <div>
                                            <label class="text-[11px] font-medium text-ink-700 block mb-1">Image URL (Public HTTPS Link)</label>
                                            <input type="url" name="header_image_url" x-model="form.header_image_url" class="input !text-xs"
                                                   placeholder="https://images.unsplash.com/... or https://yourdomain.com/storage/..."
                                                   @input="localImagePreview = ''">
                                        </div>

                                        <div>
                                            <label class="text-[11px] font-medium text-ink-700 block mb-1">Or Upload Header Image <span class="text-ink-400 font-normal">(Max 5MB • JPG, PNG, WebP)</span></label>
                                            <input type="file" name="header_image_file" accept="image/*" class="input !text-xs !p-1.5"
                                                   @change="onImageFileChange($event)">
                                        </div>

                                        {{-- Preset Travel Photos --}}
                                        <div>
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-ink-500 block mb-1">Quick Kashmir Photo Presets:</span>
                                            <div class="flex flex-wrap gap-1">
                                                <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-emerald-50 text-emerald-800 rounded text-[10px] border border-emerald-200"
                                                        @click="form.header_image_url = 'https://images.unsplash.com/photo-1595815771614-ade9d652a65d?w=800&q=80'; localImagePreview = '';">
                                                    🛶 Dal Lake Shikara
                                                </button>
                                                <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-emerald-50 text-emerald-800 rounded text-[10px] border border-emerald-200"
                                                        @click="form.header_image_url = 'https://images.unsplash.com/photo-1598091383021-15ddea10925d?w=800&q=80'; localImagePreview = '';">
                                                    ❄️ Gulmarg Snow
                                                </button>
                                                <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-emerald-50 text-emerald-800 rounded text-[10px] border border-emerald-200"
                                                        @click="form.header_image_url = 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800&q=80'; localImagePreview = '';">
                                                    🌄 Kashmir Valley
                                                </button>
                                                <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-rose-50 text-rose-800 rounded text-[10px] border border-rose-200"
                                                        @click="form.header_image_url = 'https://images.unsplash.com/photo-1621583441131-c8c190794970?w=800&q=80'; localImagePreview = '';">
                                                    🌷 Tulip Garden
                                                </button>
                                                <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 text-rose-600 rounded text-[10px]"
                                                        @click="form.header_image_url = ''; localImagePreview = '';" x-show="form.header_image_url || localImagePreview">
                                                    Clear image
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Thumbnail preview in form --}}
                                        <template x-if="localImagePreview || form.header_image_url">
                                            <div class="relative rounded-lg overflow-hidden border border-slate-200 h-24 w-full bg-slate-100 flex items-center justify-center">
                                                <img :src="localImagePreview || form.header_image_url" alt="Header Preview" class="h-full w-full object-cover">
                                                <span class="absolute bottom-1 right-1 bg-black/60 text-white text-[9px] px-1.5 py-0.5 rounded">Header Preview</span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Main Body Text --}}
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="label text-xs !mb-0">Message Body <span class="text-rose-500">*</span></label>
                                        <span class="text-[10px] text-ink-400" x-text="(form.reply_text ? form.reply_text.length : 0) + ' chars'"></span>
                                    </div>
                                    <textarea name="reply_text" x-model="form.reply_text" rows="4" class="input !text-xs font-sans"
                                              placeholder="Hello! Welcome to TravelQue Cashmir 🌸&#10;&#10;How can we assist your trip planning today? Please select an option below:"></textarea>

                                    {{-- Quick Starters --}}
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="applyStarter('menu')">
                                            📋 Welcome Menu
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="applyStarter('package')">
                                            🏔️ Package Enquiry
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="applyStarter('cab')">
                                            🚖 Cab Booking
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 bg-slate-100 hover:bg-slate-200 text-ink-700 rounded text-[10px]"
                                                @click="applyStarter('handoff')">
                                            👨‍💼 Agent Handoff
                                        </button>
                                    </div>
                                </div>

                                {{-- Footer Text (Optional) --}}
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="label text-xs !mb-0">Footer Text <span class="text-ink-400 font-normal">(Optional, max 60 chars)</span></label>
                                        <span class="text-[10px] text-ink-400" x-text="(form.footer_text ? form.footer_text.length : 0) + '/60'"></span>
                                    </div>
                                    <input type="text" name="footer_text" x-model="form.footer_text" maxlength="60" class="input !text-xs text-ink-600 italic"
                                           placeholder="e.g. 24/7 Support • Srinagar, Kashmir">
                                </div>

                                {{-- Quick Reply Buttons Builder --}}
                                <div class="rounded-xl border border-teal-200/80 bg-white p-3.5 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-bold text-teal-900 flex items-center gap-1.5">
                                                <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59"/></svg>
                                                <span>Interactive Quick Reply Buttons (Up to 3)</span>
                                            </span>
                                            <p class="text-[10px] text-ink-500 mt-0.5">Clickable buttons on WhatsApp. When tapped by customer, triggers matching keyword rules.</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-600 uppercase tracking-wider block mb-1">Button 1 (Max 20 chars)</label>
                                            <input type="text" name="buttons[]" x-model="form.buttons[0]" maxlength="20" class="input !text-xs"
                                                   placeholder="e.g. 🏔️ Tour Packages">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-600 uppercase tracking-wider block mb-1">Button 2 (Max 20 chars)</label>
                                            <input type="text" name="buttons[]" x-model="form.buttons[1]" maxlength="20" class="input !text-xs"
                                                   placeholder="e.g. 🚖 Cab Booking">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-600 uppercase tracking-wider block mb-1">Button 3 (Max 20 chars)</label>
                                            <input type="text" name="buttons[]" x-model="form.buttons[2]" maxlength="20" class="input !text-xs"
                                                   placeholder="e.g. 👨‍💼 Talk to Agent">
                                        </div>
                                    </div>

                                    {{-- Button Presets --}}
                                    <div class="flex flex-wrap items-center gap-1 pt-1">
                                        <span class="text-[10px] font-semibold text-ink-400 mr-1">Presets:</span>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-teal-50 text-teal-800 rounded text-[10px] border border-teal-200"
                                                @click="form.buttons = ['🏔️ Packages', '🚖 Book Cab', '👨‍💼 Agent']">
                                            Packages / Cab / Agent
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-teal-50 text-teal-800 rounded text-[10px] border border-teal-200"
                                                @click="form.buttons = ['💰 View Pricing', '📄 Get Quotation', '📞 Call Support']">
                                            Pricing / Quote / Call
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-2 bg-teal-50 text-teal-800 rounded text-[10px] border border-teal-200"
                                                @click="form.buttons = ['✅ Confirm Dates', '🔄 Change Plan', '❌ Cancel']">
                                            Confirm / Change / Cancel
                                        </button>
                                        <button type="button" class="btn-ghost btn-xs !py-0.5 !px-1.5 text-rose-600 rounded text-[10px]"
                                                @click="form.buttons = ['', '', '']" x-show="activeButtons.length > 0">
                                            Clear buttons
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- META TEMPLATE CHOOSER --}}
                            <div x-show="replyType === 'template'" x-transition class="space-y-3 pt-1">
                                <div class="grid grid-cols-1 sm:grid-cols-[1fr_120px] gap-3">
                                    <div>
                                        <label class="label text-xs">Approved Template <span class="text-rose-500">*</span></label>
                                        <select name="template_name" x-model="form.template_name"
                                                @change="onTemplateChange($event.target.value)" class="input !text-xs">
                                            <option value="">-- Choose Meta Template --</option>
                                            <template x-for="t in templates" :key="t.id">
                                                <option :value="t.name"
                                                        :selected="form.template_name === t.name"
                                                        x-text="t.name + ' (' + t.language + ') — ' + (t.category || 'TEMPLATE')">
                                                </option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label text-xs">Language</label>
                                        <input type="text" name="template_language" x-model="form.template_language" class="input !text-xs font-mono"
                                               placeholder="en_US">
                                    </div>
                                </div>

                                {{-- Media Header (document / image / video) — only when the template declares one --}}
                                <template x-if="selectedTemplate && ['document','image','video'].includes(selectedTemplate.header_type)">
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3 space-y-1.5">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                            <label class="label text-xs !mb-0 font-bold text-emerald-900">
                                                <span x-text="(selectedTemplate.header_type.charAt(0).toUpperCase() + selectedTemplate.header_type.slice(1))"></span>
                                                Header Link <span class="text-rose-500">*</span>
                                            </label>
                                        </div>
                                        <p class="text-[10px] text-emerald-700">This template was approved with a <strong x-text="selectedTemplate.header_type"></strong> header. Provide a public HTTPS link to the file, or upload one below, to send with every reply.</p>
                                        <div>
                                            <label class="text-[11px] font-medium text-ink-700 block mb-1">Public HTTPS Link</label>
                                            <input type="url" name="template_header_media_url" x-model="form.template_header_media_url" class="input !text-xs"
                                                   :placeholder="selectedTemplate.header_type === 'document' ? 'https://yourdomain.com/files/brochure.pdf' : 'https://yourdomain.com/images/banner.jpg'"
                                                   @input="templateMediaFileName = ''">
                                        </div>
                                        <div>
                                            <label class="text-[11px] font-medium text-ink-700 block mb-1">
                                                Or Choose File
                                                <span class="text-ink-400 font-normal">(Max 16MB •
                                                    <span x-text="selectedTemplate.header_type === 'document' ? 'PDF, DOC, DOCX' : (selectedTemplate.header_type === 'video' ? 'MP4, 3GP' : 'JPG, PNG, WebP')"></span>)
                                                </span>
                                            </label>
                                            <input type="file" name="template_header_media_file" class="input !text-xs !p-1.5"
                                                   :accept="selectedTemplate.header_type === 'document' ? '.pdf,.doc,.docx' : (selectedTemplate.header_type === 'video' ? 'video/mp4,video/3gpp' : 'image/*')"
                                                   @change="onTemplateMediaFileChange($event)">
                                            <p x-show="templateMediaFileName" class="mt-1 text-[10px] text-emerald-700 flex items-center gap-1">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                <span x-text="'Will upload: ' + templateMediaFileName"></span>
                                            </p>
                                            <p class="mt-1 text-[10px] text-ink-400">Uploading a file overrides the link above. Leave both empty to keep the current file when editing.</p>
                                        </div>
                                    </div>
                                </template>

                                {{-- Body Variables — one input per {{n}} placeholder in the template --}}
                                <template x-if="selectedTemplate && selectedTemplate.variables && selectedTemplate.variables.length > 0">
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <label class="label text-xs !mb-0 font-bold text-ink-900">Template Variables</label>
                                            <span class="text-[10px] text-ink-400" x-text="selectedTemplate.variables.length + ' required'"></span>
                                        </div>
                                        <p class="text-[10px] text-ink-500 -mt-1">Fill the values that replace each placeholder in the template body.</p>
                                        <template x-for="(v, idx) in selectedTemplate.variables" :key="v.index">
                                            <div>
                                                <label class="text-[11px] font-medium text-ink-700 flex items-center gap-1.5 mb-1">
                                                    <span class="font-mono rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600" x-text="v.placeholder"></span>
                                                    <span class="text-ink-400 text-[10px]" x-show="v.example">e.g. <span x-text="v.example"></span></span>
                                                </label>
                                                <input type="text" :name="'template_params[' + idx + ']'" x-model="form.template_params[idx]"
                                                       maxlength="1000" class="input !text-xs"
                                                       :placeholder="v.example || ('Value for ' + v.placeholder)">
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <p x-show="selectedTemplate && (!selectedTemplate.variables || selectedTemplate.variables.length === 0) && !['document','image','video'].includes(selectedTemplate.header_type)"
                                   class="text-[11px] text-ink-400 italic">This template has no variables or media header — it will send as-is.</p>
                            </div>
                        </div>

                        {{-- SECTION 3: BOT CONTROLS --}}
                        <div class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-2.5">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-600 text-white text-xs font-bold">3</span>
                                <h3 class="font-bold text-xs text-ink-900">Bot Controls & Handoff</h3>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-0.5 text-xs">
                                <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-2.5 cursor-pointer hover:bg-slate-50 transition">
                                    <input type="checkbox" name="active" value="1" x-model="form.active"
                                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <div>
                                        <span class="block font-bold text-ink-900">Rule Active</span>
                                        <span class="block text-[11px] text-ink-500">Evaluate immediately for incoming messages</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50/40 p-2.5 cursor-pointer hover:bg-amber-50 transition">
                                    <input type="checkbox" name="is_handoff" value="1" x-model="form.is_handoff"
                                           class="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                    <div>
                                        <span class="block font-bold text-amber-900">Human Agent Handoff</span>
                                        <span class="block text-[11px] text-amber-700">Pause bot for this chat so a staff member can reply</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column: Live WhatsApp Chat Bubble Preview --}}
                    <div class="lg:sticky lg:top-4 space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-ink-500 flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Live WhatsApp Preview</span>
                        </div>

                        {{-- Phone Simulator Card --}}
                        <div class="rounded-2xl border-4 border-slate-800 bg-[#efeae2] p-3 shadow-xl max-w-sm mx-auto overflow-hidden">
                            {{-- Phone Bar --}}
                            <div class="rounded-xl bg-[#075e54] text-white p-2.5 flex items-center gap-2 mb-3 shadow-sm">
                                <div class="h-7 w-7 rounded-full bg-white/20 flex items-center justify-center font-bold text-xs">TQ</div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold leading-tight truncate">{{ settings('company_name', 'TravelQue Cashmir') }}</div>
                                    <div class="text-[9px] text-emerald-200 leading-tight">Official Business Account</div>
                                </div>
                            </div>

                            {{-- Chat Bubble Container --}}
                            <div class="space-y-2">
                                {{-- Incoming Simulation Message --}}
                                <div class="flex justify-start">
                                    <div class="rounded-lg rounded-tl-none bg-white p-2 text-[11px] text-slate-800 shadow-sm max-w-[85%]">
                                        <span x-text="parsedKeywords.length > 0 ? parsedKeywords[0] : 'Hello!'"></span>
                                        <div class="text-[9px] text-slate-400 text-right mt-0.5">10:30 AM</div>
                                    </div>
                                </div>

                                {{-- Bot Response Bubble --}}
                                <div class="flex justify-end">
                                    <div class="rounded-lg rounded-tr-none bg-[#d9fdd3] text-slate-900 shadow-sm max-w-[92%] overflow-hidden border border-emerald-200/50">

                                        {{-- Image Header (if set) --}}
                                        <div x-show="replyType !== 'template' && form.header_type === 'image' && (form.header_image_url || localImagePreview)"
                                             class="w-full h-32 bg-slate-200 overflow-hidden relative border-b border-emerald-200/50">
                                            <img :src="localImagePreview || form.header_image_url" alt="Header" class="w-full h-full object-cover">
                                            <div class="absolute bottom-1 right-1 bg-black/60 text-[9px] text-white px-1.5 py-0.5 rounded font-mono">HEADER IMAGE</div>
                                        </div>

                                        {{-- Text Header (if set) --}}
                                        <div x-show="replyType !== 'template' && form.header_type === 'text' && form.header_text"
                                             class="px-3 pt-2.5 pb-1 font-bold text-xs text-slate-900"
                                             x-text="form.header_text">
                                        </div>

                                        {{-- Template media header indicator --}}
                                        <div x-show="replyType === 'template' && selectedTemplate && ['document','image','video'].includes(selectedTemplate.header_type)"
                                             class="px-3 pt-2.5">
                                            <div class="flex items-center gap-1.5 rounded-md bg-emerald-100/70 px-2 py-1 text-[10px] font-semibold text-emerald-800">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                                <span x-text="(selectedTemplate ? selectedTemplate.header_type.toUpperCase() : '') + ' HEADER'"></span>
                                            </div>
                                        </div>

                                        {{-- Body Text --}}
                                        <div class="px-3 py-1.5 text-xs whitespace-pre-line leading-relaxed"
                                             x-text="replyType === 'template' ? (templatePreviewText || 'Select a template...') : (form.reply_text || 'Type your message text...')">
                                        </div>

                                        {{-- Footer Text (if set) --}}
                                        <div x-show="replyType !== 'template' && form.footer_text"
                                             class="px-3 pt-0.5 pb-1 text-[10px] text-slate-500 italic"
                                             x-text="form.footer_text">
                                        </div>

                                        {{-- Timestamp & Ticks --}}
                                        <div class="px-3 pb-1 text-[9px] text-slate-400 text-right flex items-center justify-end gap-1">
                                            <span>10:30 AM</span>
                                            <span class="text-sky-600 font-bold">✓✓</span>
                                        </div>

                                        {{-- Interactive Buttons (if any) --}}
                                        <div x-show="replyType !== 'template' && activeButtons.length > 0"
                                             class="border-t border-emerald-200/60 divide-y divide-emerald-200/60 bg-emerald-50/40">
                                            <template x-for="(btnTitle, bIdx) in activeButtons" :key="bIdx">
                                                <div class="py-1.5 px-3 text-center text-xs font-semibold text-[#00a884] hover:bg-emerald-100/50 transition cursor-pointer flex items-center justify-center gap-1">
                                                    <svg class="h-3 w-3 text-[#00a884]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/></svg>
                                                    <span x-text="btnTitle"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <span class="text-[10px] text-ink-400 italic">Preview reflects real Meta WhatsApp Cloud delivery</span>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button type="button" @click="open = false" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm flex items-center gap-1.5 shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        <span x-text="editing ? 'Update Auto-reply Rule' : 'Save Auto-reply Rule'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('autoReplyManager', () => ({
            open: false,
            editing: null,
            triggerType: 'keyword',
            replyType: 'text',
            selectedTemplatePreview: '',
            selectedTemplateVars: 0,
            localImagePreview: '',
            templateMediaFileName: '',
            templates: @json($templates),
            form: {
                name: '',
                match_type: 'contains',
                keywords_raw: '',
                reply_type: 'text',
                reply_text: '',
                header_type: 'text',
                header_image_url: '',
                header_text: '',
                footer_text: '',
                buttons: ['', '', ''],
                template_name: '',
                template_language: 'en_US',
                template_params: [],
                template_header_media_url: '',
                is_default: false,
                is_handoff: false,
                active: true,
                priority: 100,
            },

            init() {
                // Component ready
            },

            openNew() {
                this.editing = null;
                this.triggerType = 'keyword';
                this.replyType = 'text';
                this.localImagePreview = '';
                this.templateMediaFileName = '';
                this.form = {
                    name: '',
                    match_type: 'contains',
                    keywords_raw: '',
                    reply_type: 'text',
                    reply_text: '',
                    header_type: 'text',
                    header_image_url: '',
                    header_text: '',
                    footer_text: '',
                    buttons: ['', '', ''],
                    template_name: this.templates.length > 0 ? this.templates[0].name : '',
                    template_language: this.templates.length > 0 ? (this.templates[0].language || 'en_US') : 'en_US',
                    template_params: [],
                    template_header_media_url: '',
                    is_default: false,
                    is_handoff: false,
                    active: true,
                    priority: 100,
                };
                this.selectedTemplatePreview = '';
                this.selectedTemplateVars = 0;
                this.open = true;
            },

            openEdit(rule) {
                this.editing = rule;
                this.triggerType = rule.is_default ? 'default' : 'keyword';
                this.replyType = rule.reply_type === 'template' ? 'template' : 'text';
                this.localImagePreview = '';
                this.templateMediaFileName = '';

                const existingButtons = Array.isArray(rule.buttons) ? rule.buttons.map(b => typeof b === 'object' ? (b.title || '') : String(b)) : [];
                const paddedButtons = [
                    existingButtons[0] || '',
                    existingButtons[1] || '',
                    existingButtons[2] || '',
                ];

                this.form = {
                    id: rule.id,
                    name: rule.name || '',
                    match_type: rule.match_type || 'contains',
                    keywords_raw: rule.keywords_raw || '',
                    reply_type: rule.reply_type || 'text',
                    reply_text: rule.reply_text || '',
                    header_type: rule.header_type || (rule.header_image_url ? 'image' : (rule.header_text ? 'text' : 'none')),
                    header_image_url: rule.header_image_url || '',
                    header_text: rule.header_text || '',
                    footer_text: rule.footer_text || '',
                    buttons: paddedButtons,
                    template_name: rule.template_name || '',
                    template_language: rule.template_language || 'en_US',
                    template_params: Array.isArray(rule.template_params) ? rule.template_params.map(v => String(v ?? '')) : [],
                    template_header_media_url: rule.template_header_media_url || '',
                    is_default: !!rule.is_default,
                    is_handoff: !!rule.is_handoff,
                    active: !!rule.active,
                    priority: rule.priority ?? 100,
                };
                if (this.form.template_name) {
                    this.onTemplateChange(this.form.template_name, true);
                }
                this.open = true;
            },

            setHeaderType(type) {
                this.form.header_type = type;
            },

            onImageFileChange(e) {
                const file = e.target.files && e.target.files[0];
                if (file) {
                    this.localImagePreview = URL.createObjectURL(file);
                }
            },

            onTemplateMediaFileChange(e) {
                const file = e.target.files && e.target.files[0];
                this.templateMediaFileName = file ? file.name : '';
            },

            setTriggerType(type) {
                this.triggerType = type;
                this.form.is_default = (type === 'default');
            },

            setReplyType(type) {
                this.replyType = type;
                this.form.reply_type = type;
                if (type === 'template' && !this.form.template_name && this.templates.length > 0) {
                    this.form.template_name = this.templates[0].name;
                    this.onTemplateChange(this.templates[0].name);
                }
            },

            onTemplateChange(name, preserveParams = false) {
                const t = this.templates.find(tpl => tpl.name === name);
                if (t) {
                    this.form.template_language = t.language || 'en_US';
                    this.selectedTemplatePreview = t.body_preview || '';
                    const varCount = (t.variables && t.variables.length) ? t.variables.length : (t.body_variable_count || 0);
                    this.selectedTemplateVars = varCount;

                    // Resize the params array to exactly match the template's variable
                    // count. Keep already-entered values (editing) up to the count.
                    const existing = preserveParams && Array.isArray(this.form.template_params) ? this.form.template_params : [];
                    const next = [];
                    for (let i = 0; i < varCount; i++) {
                        next[i] = existing[i] != null ? String(existing[i]) : '';
                    }
                    this.form.template_params = next;

                    // Clear a stale media link if the new template has no media header.
                    const hasMediaHeader = ['document', 'image', 'video'].includes(t.header_type);
                    if (!hasMediaHeader) {
                        this.form.template_header_media_url = '';
                    }
                } else {
                    this.selectedTemplatePreview = '';
                    this.selectedTemplateVars = 0;
                    this.form.template_params = [];
                    this.form.template_header_media_url = '';
                }
            },

            get selectedTemplate() {
                return this.templates.find(tpl => tpl.name === this.form.template_name) || null;
            },

            get templatePreviewText() {
                let text = this.selectedTemplatePreview || '';
                const params = this.form.template_params || [];
                return text.replace(/\{\{(\d+)\}\}/g, (m, n) => {
                    const val = params[parseInt(n, 10) - 1];
                    return (val && String(val).trim() !== '') ? val : m;
                });
            },

            addKeywordTag(tag) {
                let current = (this.form.keywords_raw || '').trim();
                let tags = current ? current.split(/[\n,]+/).map(s => s.trim()).filter(Boolean) : [];
                if (!tags.map(t => t.toLowerCase()).includes(tag.toLowerCase())) {
                    tags.push(tag.toLowerCase());
                    this.form.keywords_raw = tags.join(', ');
                }
            },

            applyStarter(type) {
                if (type === 'menu') {
                    this.form.header_type = 'image';
                    this.form.header_image_url = 'https://images.unsplash.com/photo-1595815771614-ade9d652a65d?w=800&q=80';
                    this.localImagePreview = '';
                    this.form.header_text = '';
                    this.form.reply_text = 'Hello and welcome! 🌸 How may we help you experience Kashmir today? Please tap an option below:';
                    this.form.footer_text = '24/7 Travel Assistance • Ishber Nishat';
                    this.form.buttons = ['🏔️ Tour Packages', '🚖 Cab Booking', '👨‍💼 Talk to Agent'];
                } else if (type === 'package') {
                    this.form.header_type = 'image';
                    this.form.header_image_url = 'https://images.unsplash.com/photo-1598091383021-15ddea10925d?w=800&q=80';
                    this.localImagePreview = '';
                    this.form.header_text = '';
                    this.form.reply_text = 'Explore our best-selling Kashmir holiday packages including private cabs, houseboat stays, and guided sightseeing. Tap below for instant options:';
                    this.form.footer_text = 'Customizable Itineraries Available';
                    this.form.buttons = ['🏔️ Gulmarg & Pahalgam', '💰 View Package Rates', '📞 Request Callback'];
                } else if (type === 'cab') {
                    this.form.header_type = 'text';
                    this.form.header_image_url = '';
                    this.localImagePreview = '';
                    this.form.header_text = '🚖 Private Cabs & Airport Transfers';
                    this.form.reply_text = 'We offer verified commercial cabs (Innova, Crysta, Etios, Tempo Traveller) with professional drivers across Jammu & Kashmir:';
                    this.form.footer_text = 'Sanitized • On-Time Guarantee';
                    this.form.buttons = ['✈️ Airport Pickup', '🏔️ Day Trip Cab', '👨‍💼 Speak to Driver'];
                } else if (type === 'handoff') {
                    this.form.header_type = 'text';
                    this.form.header_image_url = '';
                    this.localImagePreview = '';
                    this.form.header_text = '👨‍💼 Travel Consultant Connecting';
                    this.form.reply_text = 'One of our dedicated travel consultants has been assigned to this chat and will reply shortly!';
                    this.form.footer_text = 'Helpline: +91 70069 76447';
                    this.form.buttons = ['📞 Call Us Now', '⏱️ Request Callback', '🔙 Main Menu'];
                    this.form.is_handoff = true;
                }
            },

            get parsedKeywords() {
                if (!this.form.keywords_raw) return [];
                return this.form.keywords_raw.split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
            },

            get activeButtons() {
                return (this.form.buttons || []).map(b => b.trim()).filter(Boolean);
            }
        }));
    });
</script>
@endpush
