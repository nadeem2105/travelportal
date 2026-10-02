@extends('emails.layout')

@section('content')
<div style="margin-bottom: 24px;">
    <span class="badge" style="background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 4px 10px;">
        🚨 Human Agent Required
    </span>
    <h2 style="margin: 12px 0 6px 0; color: #0f172a; font-size: 20px;">
        WhatsApp Conversation Hand-off
    </h2>
    <p style="margin: 0; color: #64748b; font-size: 14px;">
        A customer has requested human assistance or matched a handoff rule on WhatsApp. The chatbot has been paused for this conversation.
    </p>
</div>

<!-- Customer & Message Details Box -->
<div class="details-box" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 20px 0;">
    <div class="details-row">
        <span class="details-label">Customer Name:</span>
        <span class="details-val">{{ $customerName }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">WhatsApp Phone (WA ID):</span>
        <span class="details-val font-mono">{{ $conversation->wa_id }}</span>
    </div>
    @if ($conversation->assignee)
    <div class="details-row">
        <span class="details-label">Assigned Staff Member:</span>
        <span class="details-val">{{ $conversation->assignee->name }} ({{ $conversation->assignee->email }})</span>
    </div>
    @endif
    <div class="details-row">
        <span class="details-label">Triggered Handoff Rule:</span>
        <span class="details-val font-semibold text-brand-700">{{ $rule->name }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">Hand-off Time:</span>
        <span class="details-val">{{ now()->format('d M Y, h:i A') }}</span>
    </div>
</div>

<!-- Customer's Last Message -->
@if ($inboundText)
<div style="margin: 20px 0; background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 14px 18px; border-radius: 6px;">
    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #15803d; margin-bottom: 6px;">
        💬 Customer's Last Inbound Message:
    </div>
    <div style="font-size: 14px; color: #0f172a; white-space: pre-wrap; font-style: italic;">
        "{{ $inboundText }}"
    </div>
</div>
@endif

<!-- Direct CTA to open WhatsApp Inbox in Admin -->
<div style="text-align: center; margin: 30px 0 20px 0;">
    <a href="{{ $inboxUrl }}" class="btn" style="background-color: #0f766e; color: #ffffff !important; padding: 14px 28px; font-size: 15px; font-weight: 700; border-radius: 8px; text-decoration: none; display: inline-block;">
        👉 Open Chat in WhatsApp Inbox & Reply
    </a>
    <p style="margin: 10px 0 0 0; font-size: 12px; color: #64748b;">
        Clicking the button will open this conversation directly in the TravelQue Cashmir CRM WhatsApp Inbox.
    </p>
</div>
@endsection
