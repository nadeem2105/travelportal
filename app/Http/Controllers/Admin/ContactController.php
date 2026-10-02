<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Contact;
use App\Services\ActivityLogger;
use App\Services\Crm\ContactService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $contacts = Contact::query()
            ->with(['assignee', 'leadSource'])
            ->withCount('leads')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('alternate_phone', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->when($request->filled('lifecycle_stage'), fn ($query) => $query->where('lifecycle_stage', $request->query('lifecycle_stage')))
            ->when($request->filled('assigned_user'), fn ($query) => $query->where('assigned_user_id', $request->query('assigned_user')))
            ->latest()
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        $staff = Admin::where('status', 'active')->orderBy('name')->get();
        $lifecycleStages = ['lead', 'prospect', 'qualified', 'customer', 'repeat', 'vip', 'inactive'];

        return view('admin.crm.contacts.index', compact('contacts', 'staff', 'lifecycleStages'));
    }

    public function store(Request $request, ContactService $contacts)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'alternate_phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:180',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'lifecycle_stage' => 'nullable|in:lead,prospect,qualified,customer,repeat,vip,inactive',
            'source_id' => 'nullable|exists:lead_sources,id',
            'assigned_user_id' => 'nullable|exists:admins,id',
            'marketing_opt_in' => 'nullable|boolean',
            'whatsapp_opt_in' => 'nullable|boolean',
            'email_opt_in' => 'nullable|boolean',
            'sms_opt_in' => 'nullable|boolean',
        ]);

        if (empty($validated['phone']) && empty($validated['email'])) {
            return back()->withInput()->with('error', 'A contact needs at least a phone number or an email address.');
        }

        // Reuse identity resolution so a manual add never duplicates an existing contact.
        $existing = $contacts->findExisting($validated['phone'] ?? null, $validated['email'] ?? null);
        if ($existing) {
            return redirect()
                ->route('admin.contacts.show', $existing)
                ->with('info', "A contact with this phone/email already exists: “{$existing->name}”. Showing the existing record.");
        }

        $contact = $contacts->findOrCreate([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'address' => $validated['address'] ?? null,
            'source_id' => $validated['source_id'] ?? null,
            'lifecycle_stage' => $validated['lifecycle_stage'] ?? 'lead',
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
            'email_opt_in' => $request->boolean('email_opt_in', true),
            'sms_opt_in' => $request->boolean('sms_opt_in', true),
        ]);

        // Fields findOrCreate doesn't handle: alternate phone + manual assignment.
        $extra = [];
        if (! empty($validated['alternate_phone'])) {
            $extra['alternate_phone'] = $contacts->normalizePhone($validated['alternate_phone']);
        }
        if (! empty($validated['assigned_user_id'])) {
            $extra['assigned_user_id'] = $validated['assigned_user_id'];
        }
        if ($extra) {
            $contact->fill($extra)->save();
        }

        ActivityLogger::log('create', 'crm', "Manually added contact #{$contact->id} ({$contact->name})");

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', "Contact “{$contact->name}” added.");
    }

    public function update(Request $request, Contact $contact, ContactService $contacts)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'alternate_phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:180',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'lifecycle_stage' => 'nullable|in:lead,prospect,qualified,customer,repeat,vip,inactive',
            'source_id' => 'nullable|exists:lead_sources,id',
            'assigned_user_id' => 'nullable|exists:admins,id',
            'notes' => 'nullable|string|max:2000',
            'marketing_opt_in' => 'nullable|boolean',
            'whatsapp_opt_in' => 'nullable|boolean',
            'email_opt_in' => 'nullable|boolean',
            'sms_opt_in' => 'nullable|boolean',
        ]);

        if (empty($validated['phone']) && empty($validated['email'])) {
            return back()->withInput()->with('error', 'A contact needs at least a phone number or an email address.');
        }

        $phone = $contacts->normalizePhone($validated['phone'] ?? null);
        $email = $contacts->normalizeEmail($validated['email'] ?? null);

        // Guard: don't let an edit collide with a DIFFERENT existing contact's phone/email.
        $clash = Contact::where('id', '!=', $contact->id)
            ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->first();
        if ($clash) {
            return back()->withInput()->with('error', "Another contact (“{$clash->name}”) already uses that phone or email. Use Merge instead of duplicating.");
        }

        $contact->fill([
            'name' => $validated['name'],
            'phone' => $phone,
            'phone_raw' => $validated['phone'] ?? null,
            'alternate_phone' => $contacts->normalizePhone($validated['alternate_phone'] ?? null),
            'email' => $email,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'address' => $validated['address'] ?? null,
            'lifecycle_stage' => $validated['lifecycle_stage'] ?? $contact->lifecycle_stage,
            'source_id' => $validated['source_id'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
            'email_opt_in' => $request->boolean('email_opt_in'),
            'sms_opt_in' => $request->boolean('sms_opt_in'),
        ])->save();

        ActivityLogger::log('update', 'crm', "Updated contact #{$contact->id} ({$contact->name})");

        return redirect()->route('admin.contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function show(Contact $contact)
    {
        $contact->load([
            'assignee', 'company', 'leadSource', 'user.bookings',
            'leads.stage', 'leads.quotations', 'leads.assignee',
            'activities.performer', 'tasks.assignee',
        ]);

        // Quotations for this contact, gathered via their leads.
        $quotations = $contact->leads->flatMap->quotations->sortByDesc('created_at');

        // Candidate secondaries for a merge (everyone else, capped for the picker).
        $mergeCandidates = Contact::where('id', '!=', $contact->id)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'phone', 'email']);

        // For the edit form.
        $staff = Admin::where('status', 'active')->orderBy('name')->get();
        $sources = \App\Models\LeadSource::orderBy('name')->get();
        $lifecycleStages = ['lead', 'prospect', 'qualified', 'customer', 'repeat', 'vip', 'inactive'];

        return view('admin.crm.contacts.show', compact('contact', 'quotations', 'mergeCandidates', 'staff', 'sources', 'lifecycleStages'));
    }

    public function merge(Request $request, Contact $contact, ContactService $contacts)
    {
        $validated = $request->validate([
            'secondary_id' => 'required|exists:contacts,id',
        ]);

        if ((int) $validated['secondary_id'] === (int) $contact->id) {
            return back()->with('error', 'You cannot merge a contact into itself.');
        }

        $secondary = Contact::findOrFail($validated['secondary_id']);
        $contacts->merge($contact, $secondary);

        ActivityLogger::log('update', 'crm', "Merged contact #{$secondary->id} into #{$contact->id} ({$contact->name})");

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', "Contact “{$secondary->name}” merged into “{$contact->name}”.");
    }
}
