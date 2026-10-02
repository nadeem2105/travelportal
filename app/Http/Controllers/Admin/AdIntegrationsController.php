<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\WebhookEvent;

/**
 * Read-only status page for the Meta Lead Ads and Google Ads lead webhooks:
 * config state (masked), the callback URLs to paste into Meta/Google, and a
 * summary of leads ingested from each channel. Credentials live in .env.
 */
class AdIntegrationsController extends Controller
{
    public function index()
    {
        $meta = config('services.meta_leads');
        $google = config('services.google_leads');

        $status = [
            'meta' => [
                'enabled' => (bool) ($meta['enabled'] ?? false),
                'verify_token' => $meta['verify_token'] ?? null,
                'app_secret' => ! empty($meta['app_secret']),
                'page_access_token' => $this->mask($meta['page_access_token'] ?? null),
                'webhook_url' => url('/webhooks/meta'),
                'ingested' => WebhookEvent::where('provider', 'meta_leads')->count(),
            ],
            'google' => [
                'enabled' => (bool) ($google['enabled'] ?? false),
                'key' => $this->mask($google['key'] ?? null),
                'webhook_url' => url('/webhooks/google-leads'),
                'ingested' => WebhookEvent::where('provider', 'google_leads')->count(),
            ],
        ];

        // Recent leads from paid ad sources.
        $recentLeads = CrmLead::whereHas('leadSource', fn ($q) => $q->whereIn('slug', ['meta-ads', 'google-ads']))
            ->with('leadSource')
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.crm.ad_integrations', compact('status', 'recentLeads'));
    }

    private function mask(?string $secret): ?string
    {
        if (empty($secret)) {
            return null;
        }

        return strlen($secret) <= 8 ? str_repeat('•', strlen($secret)) : ('••••••••' . substr($secret, -4));
    }
}
