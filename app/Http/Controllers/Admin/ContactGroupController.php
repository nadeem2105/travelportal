<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\LeadSource;
use App\Models\Tag;
use Illuminate\Http\Request;

class ContactGroupController extends Controller
{
    public function index()
    {
        $groups = ContactGroup::withCount('members')->latest()->paginate(20);

        return view('admin.crm.groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.crm.groups.form', [
            'group' => new ContactGroup(['type' => 'static']),
            'sources' => LeadSource::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'lifecycleStages' => ['lead', 'prospect', 'qualified', 'customer', 'repeat', 'vip', 'inactive'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateGroup($request);
        $data['created_by'] = auth('admin')->id();
        $group = ContactGroup::create($data);

        return redirect()->route('admin.contact-groups.show', $group)->with('success', 'Group created.');
    }

    public function show(ContactGroup $contactGroup)
    {
        $members = $contactGroup->contactsQuery()->paginate(30);

        // For static groups, offer a picker of contacts not yet in the group.
        $candidates = $contactGroup->isDynamic()
            ? collect()
            : Contact::whereDoesntHave('contactGroups', fn ($q) => $q->where('contact_groups.id', $contactGroup->id))
                ->orderBy('name')->limit(50)->get(['id', 'name', 'phone', 'email']);

        return view('admin.crm.groups.show', compact('contactGroup', 'members', 'candidates'));
    }

    public function edit(ContactGroup $contactGroup)
    {
        return view('admin.crm.groups.form', [
            'group' => $contactGroup,
            'sources' => LeadSource::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'lifecycleStages' => ['lead', 'prospect', 'qualified', 'customer', 'repeat', 'vip', 'inactive'],
        ]);
    }

    public function update(Request $request, ContactGroup $contactGroup)
    {
        $contactGroup->update($this->validateGroup($request));

        return redirect()->route('admin.contact-groups.show', $contactGroup)->with('success', 'Group updated.');
    }

    public function destroy(ContactGroup $contactGroup)
    {
        $contactGroup->delete();

        return redirect()->route('admin.contact-groups.index')->with('success', 'Group deleted.');
    }

    public function addMember(Request $request, ContactGroup $contactGroup)
    {
        if ($contactGroup->isDynamic()) {
            return back()->with('error', 'Dynamic groups resolve members automatically.');
        }
        $validated = $request->validate(['contact_id' => 'required|exists:contacts,id']);
        $contactGroup->members()->syncWithoutDetaching([$validated['contact_id']]);

        return back()->with('success', 'Contact added to group.');
    }

    public function removeMember(ContactGroup $contactGroup, Contact $contact)
    {
        $contactGroup->members()->detach($contact->id);

        return back()->with('success', 'Contact removed from group.');
    }

    private function validateGroup(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:static,dynamic',
            'description' => 'nullable|string|max:500',
            'filter_lifecycle_stage' => 'nullable|string|max:30',
            'filter_source_id' => 'nullable|exists:lead_sources,id',
            'filter_tag_id' => 'nullable|exists:tags,id',
            'filter_whatsapp_opt_in' => 'nullable|in:0,1',
        ]);

        $filters = null;
        if ($validated['type'] === 'dynamic') {
            $filters = array_filter([
                'lifecycle_stage' => $validated['filter_lifecycle_stage'] ?? null,
                'source_id' => $validated['filter_source_id'] ?? null,
                'tag_id' => $validated['filter_tag_id'] ?? null,
                'whatsapp_opt_in' => $request->filled('filter_whatsapp_opt_in') ? (int) $validated['filter_whatsapp_opt_in'] : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'filters' => $filters,
        ];
    }
}
