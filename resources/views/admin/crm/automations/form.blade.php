@extends('layouts.admin')
@section('pageTitle', $workflow->exists ? 'Edit Workflow' : 'New Workflow')

@php
    $statuses = ['new', 'contacted', 'quotation_sent', 'negotiating', 'converted', 'lost'];
    $serviceTypes = ['package', 'flight', 'hotel', 'cab', 'custom'];
    // Flatten existing actions into editable rows for Alpine.
    $initActions = old('actions', $workflow->actions->map(fn ($a) => array_merge([
        'type' => $a->type,
        'delay_minutes' => $a->delay_minutes,
        'template' => '', 'lang' => '', 'params' => '', 'subject' => '', 'body' => '',
        'title' => '', 'task_type' => 'follow_up', 'priority' => 'medium', 'due_in_days' => '',
        'stage_id' => '', 'assigned_to' => 'round_robin', 'tag_id' => '', 'note' => '',
    ], collect($a->config ?? [])->map(fn ($v) => is_array($v) ? implode('|', $v) : $v)->all()))->values()->all());
    $cond = $workflow->conditions ?? [];
@endphp

@section('content')
<a href="{{ route('admin.automations.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Workflows</a>
<h1 class="mt-2 font-display text-xl font-bold">{{ $workflow->exists ? 'Edit Workflow' : 'New Automation Workflow' }}</h1>

@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif

<form action="{{ $workflow->exists ? route('admin.automations.update', $workflow) : route('admin.automations.store') }}"
      method="POST" class="mt-4 space-y-4"
      x-data='{
        trigger: @json(old("trigger_event", $workflow->trigger_event ?? "lead_created")),
        actions: @json($initActions ?: []),
        addAction() { this.actions.push({ type: "send_whatsapp", delay_minutes: 0, template: "", lang: "", params: "", subject: "", body: "", title: "", task_type: "follow_up", priority: "medium", due_in_days: "", stage_id: "", assigned_to: "round_robin", tag_id: "", note: "" }); },
        removeAction(i) { this.actions.splice(i, 1); }
      }'>
    @csrf
    @if ($workflow->exists) @method('PUT') @endif

    {{-- Basics --}}
    <div class="admin-card p-5 space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Workflow Name *</label>
                <input type="text" name="name" value="{{ old('name', $workflow->name) }}" required class="input" placeholder="e.g. New lead welcome">
            </div>
            <div>
                <label class="label">Trigger *</label>
                <select name="trigger_event" x-model="trigger" class="input">
                    @foreach ($triggers as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
        <div x-show="trigger === 'no_activity'" x-cloak>
            <label class="label">Trigger after (days of no activity)</label>
            <input type="number" name="trigger_days" min="1" max="365" value="{{ old('trigger_days', $workflow->trigger_config['days'] ?? 3) }}" class="input w-32">
        </div>
        <div>
            <label class="label">Description</label>
            <input type="text" name="description" value="{{ old('description', $workflow->description) }}" class="input">
        </div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $workflow->is_active ?? true))> Active</label>
    </div>

    {{-- Conditions --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Conditions <span class="font-normal text-ink-400">(all must match — leave blank to skip)</span></h3>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="label">Source</label>
                <select name="cond_source_id" class="input">
                    <option value="">Any</option>
                    @foreach ($sources as $s)<option value="{{ $s->id }}" @selected((string)($cond['source_id'] ?? '') === (string)$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Stage</label>
                <select name="cond_stage_id" class="input">
                    <option value="">Any</option>
                    @foreach ($stages as $s)<option value="{{ $s->id }}" @selected((string)($cond['stage_id'] ?? '') === (string)$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select name="cond_status" class="input">
                    <option value="">Any</option>
                    @foreach ($statuses as $st)<option value="{{ $st }}" @selected(($cond['status'] ?? '') === $st)>{{ label_case($st) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Service type</label>
                <select name="cond_service_type" class="input">
                    <option value="">Any</option>
                    @foreach ($serviceTypes as $t)<option value="{{ $t }}" @selected(($cond['service_type'] ?? '') === $t)>{{ ucfirst($t) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Min. lead score</label>
                <input type="number" name="cond_min_score" min="0" value="{{ $cond['min_score'] ?? '' }}" class="input">
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="admin-card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-bold">Actions <span class="font-normal text-ink-400">(run in order)</span></h3>
            <button type="button" @click="addAction()" class="btn-ghost btn-sm">+ Add action</button>
        </div>

        <template x-if="actions.length === 0"><p class="py-3 text-xs text-ink-400">No actions yet — add at least one.</p></template>

        <div class="space-y-3">
            <template x-for="(a, i) in actions" :key="i">
                <div class="rounded-lg border p-3">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1">
                            <label class="label">Action</label>
                            <select :name="'actions['+i+'][type]'" x-model="a.type" class="input text-sm">
                                @foreach ($actionTypes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="w-32">
                            <label class="label">Delay (min)</label>
                            <input type="number" min="0" :name="'actions['+i+'][delay_minutes]'" x-model="a.delay_minutes" class="input text-sm" title="0 = immediate; 1440 = 1 day">
                        </div>
                        <button type="button" @click="removeAction(i)" class="btn-ghost btn-sm text-rose-600">Remove</button>
                    </div>

                    {{-- send_whatsapp --}}
                    <div x-show="a.type === 'send_whatsapp'" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <input type="text" :name="'actions['+i+'][template]'" x-model="a.template" class="input text-sm" placeholder="Approved template name">
                        <input type="text" :name="'actions['+i+'][lang]'" x-model="a.lang" class="input text-sm" placeholder="Language (e.g. en_US)">
                        <input type="text" :name="'actions['+i+'][params]'" x-model="a.params" class="input text-sm sm:col-span-2" placeholder="Body params separated by | e.g. @{{name}}|@{{destination}}">
                    </div>

                    {{-- send_email --}}
                    <div x-show="a.type === 'send_email'" class="mt-2 space-y-2">
                        <input type="text" :name="'actions['+i+'][subject]'" x-model="a.subject" class="input text-sm" placeholder="Email subject (supports @{{name}})">
                        <textarea :name="'actions['+i+'][body]'" x-model="a.body" rows="3" class="input text-sm" placeholder="Email body (supports @{{name}}, @{{destination}})"></textarea>
                    </div>

                    {{-- create_task --}}
                    <div x-show="a.type === 'create_task'" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-4">
                        <input type="text" :name="'actions['+i+'][title]'" x-model="a.title" class="input text-sm sm:col-span-2" placeholder="Task title">
                        <select :name="'actions['+i+'][priority]'" x-model="a.priority" class="input text-sm">
                            <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                        </select>
                        <input type="number" min="0" :name="'actions['+i+'][due_in_days]'" x-model="a.due_in_days" class="input text-sm" placeholder="Due in days">
                    </div>

                    {{-- change_stage --}}
                    <div x-show="a.type === 'change_stage'" class="mt-2">
                        <select :name="'actions['+i+'][stage_id]'" x-model="a.stage_id" class="input text-sm">
                            <option value="">Select stage…</option>
                            @foreach ($stages as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>

                    {{-- assign_agent --}}
                    <div x-show="a.type === 'assign_agent'" class="mt-2">
                        <select :name="'actions['+i+'][assigned_to]'" x-model="a.assigned_to" class="input text-sm">
                            <option value="round_robin">Round-robin (auto)</option>
                            @foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>

                    {{-- add_tag --}}
                    <div x-show="a.type === 'add_tag'" class="mt-2">
                        <select :name="'actions['+i+'][tag_id]'" x-model="a.tag_id" class="input text-sm">
                            <option value="">Select tag…</option>
                            @foreach ($tags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                    </div>

                    {{-- add_note --}}
                    <div x-show="a.type === 'add_note'" class="mt-2">
                        <textarea :name="'actions['+i+'][note]'" x-model="a.note" rows="2" class="input text-sm" placeholder="Internal note (supports @{{name}})"></textarea>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.automations.index') }}" class="btn-ghost btn-sm">Cancel</a>
        <button class="btn-primary btn-sm">{{ $workflow->exists ? 'Save Workflow' : 'Create Workflow' }}</button>
    </div>
</form>
@endsection
