@extends('layouts.admin')
@section('pageTitle', 'Trip Operations Settings')

@php
    $offsets = $settings['driver_reminder_offsets'] ?? ['1_day', '2_hours'];
    $driverChannels = $settings['notify_driver_channels'] ?? ['whatsapp'];
    $customerChannels = $settings['notify_customer_channels'] ?? ['whatsapp', 'email'];
@endphp

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-xl font-bold">Trip Operations Settings</h1>
                <p class="text-xs text-ink-500">Automation, reminders &amp; notification channels</p>
            </div>
        </div>

        <form action="{{ route('admin.trip-ops.settings.update') }}" method="POST" class="mt-5 space-y-5">
            @csrf
            @method('PUT')

            {{-- Itinerary automation --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Itinerary Automation</h2>
                <div class="mt-3 space-y-3 text-sm">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="auto_generate_itinerary" value="1" @checked($settings['auto_generate_itinerary'] ?? true) class="mt-0.5 rounded">
                        <span>
                            <span class="font-medium text-ink-800">Auto-generate itinerary on booking confirmation</span>
                            <span class="block text-xs text-ink-500">Builds the day-by-day trip plan automatically when a booking is confirmed.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="send_customer_itinerary_on_generate" value="1" @checked($settings['send_customer_itinerary_on_generate'] ?? false) class="mt-0.5 rounded">
                        <span>
                            <span class="font-medium text-ink-800">Send customer itinerary immediately on generation</span>
                            <span class="block text-xs text-ink-500">Dispatches the customer-facing itinerary (WhatsApp/email) as soon as it is generated.</span>
                        </span>
                    </label>
                </div>
            </div>

            {{-- Tomorrow's plan --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Tomorrow's Plan (nightly customer comms)</h2>
                <div class="mt-3 space-y-3 text-sm">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="send_tomorrow_plan" value="1" @checked($settings['send_tomorrow_plan'] ?? false) class="mt-0.5 rounded">
                        <span>
                            <span class="font-medium text-ink-800">Send "Tomorrow's Plan" to customers</span>
                            <span class="block text-xs text-ink-500">A nightly summary of the next day's schedule for in-progress trips.</span>
                        </span>
                    </label>
                    <div class="max-w-[200px]">
                        <label class="label">Send time</label>
                        <input type="time" name="tomorrow_plan_time" value="{{ $settings['tomorrow_plan_time'] ?? '19:00' }}" class="input">
                    </div>
                </div>
            </div>

            {{-- Driver reminders --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Driver Reminders</h2>
                <p class="mt-1 text-xs text-ink-500">When to remind assigned drivers before pickup.</p>
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    @foreach (['1_day' => '1 day before', '12_hours' => '12 hours before', '2_hours' => '2 hours before'] as $val => $label)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="driver_reminder_offsets[]" value="{{ $val }}" @checked(in_array($val, $offsets, true)) class="rounded">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Notification channels --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Notification Channels</h2>
                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <p class="label">Driver channels</p>
                        <div class="mt-1 flex flex-col gap-2 text-sm">
                            @foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS'] as $val => $label)
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="notify_driver_channels[]" value="{{ $val }}" @checked(in_array($val, $driverChannels, true)) class="rounded">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="label">Customer channels</p>
                        <div class="mt-1 flex flex-col gap-2 text-sm">
                            @foreach (['whatsapp' => 'WhatsApp', 'email' => 'Email', 'sms' => 'SMS'] as $val => $label)
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="notify_customer_channels[]" value="{{ $val }}" @checked(in_array($val, $customerChannels, true)) class="rounded">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary btn-md">Save Settings</button>
            </div>
        </form>
    </div>
@endsection
