<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Services\Crm\LeadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function show(Request $request, Page $page)
    {
        abort_unless($page->status === 'published', 404);

        return view('pages.show', [
            'seo' => app(\App\Services\SeoService::class)->forPage('page:' . $page->slug, null, [
                'title' => $page->title,
                'description' => str($page->content)->limit(150),
            ]),
            'page' => $page,
        ]);
    }

    public function faq()
    {
        return view('pages.faq', [
            'seo' => app(\App\Services\SeoService::class)->forPage('faq', null, ['title' => 'Frequently Asked Questions']),
            'faqs' => Faq::where('status', 'active')->orderBy('sort_order')->get()->groupBy('category'),
        ]);
    }

    public function contact()
    {
        return view('pages.contact', [
            'seo' => app(\App\Services\SeoService::class)->forPage('contact', null, ['title' => 'Contact Us']),
        ]);
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:15',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:3000',
        ]);

        ContactMessage::create($validated + ['is_read' => false]);

        $this->notifications->send('contact_received', [
            'name' => $validated['name'],
            'subject' => $validated['subject'],
        ], $validated['email']);

        // Also funnel the inquiry into the CRM (dedup/attribution/assignment/
        // scoring). Best-effort: a CRM hiccup must never break the contact form.
        try {
            app(LeadService::class)->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'product_type' => 'package',
                'source' => 'website',
                'source_slug' => 'contact-form',
                'source_detail' => $validated['subject'],
                'notes' => $validated['message'],
            ], $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Thank you for reaching out! Our team will get back to you within 24 hours.');
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email|unique:newsletter_subscribers,email']);

        NewsletterSubscriber::create(['email' => $validated['email']]);

        return back()->with('success', 'You are subscribed! Watch your inbox for exclusive Kashmir deals.');
    }
}
