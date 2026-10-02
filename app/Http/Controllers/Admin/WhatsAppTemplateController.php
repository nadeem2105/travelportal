<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = WhatsAppTemplate::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->query('q') . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.crm.whatsapp.templates', compact('templates'));
    }

    public function sync(WhatsAppTemplateService $service)
    {
        $result = $service->sync();

        return $result['success']
            ? back()->with('success', "Synced {$result['synced']} template(s) from Meta.")
            : back()->with('error', 'Template sync failed: ' . ($result['error'] ?? 'unknown error'));
    }

    public function create(Request $request)
    {
        // Ready-made transactional templates the app already knows how to use.
        $presets = [
            'booking_confirmed' => [
                'label' => 'Booking Confirmation (Text Only)',
                'category' => 'UTILITY',
                'header_type' => 'none',
                'body' => "Hi {{1}}, your booking {{2}} to {{3}} is confirmed \u{2705}\nTravel date: {{4}}\nAmount paid: {{5}}\nThank you for choosing Leemroz Travels!",
                'examples' => ['Aisha', 'TQC-ABC123', 'Kashmir', '20 Oct 2026', "\u{20B9}57,750.00"],
            ],
            'booking_confirmed_itinerary' => [
                'label' => 'Booking Confirmation (with Itinerary / Voucher PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, your booking {{2}} to {{3}} is confirmed \u{2705}\n\nPlease find your official travel itinerary and booking voucher attached.\n\nTravel date: {{4}}\nAmount paid: {{5}}\n\nThank you for choosing Leemroz Travels! If you have any questions, feel free to reply to this message.",
                'examples' => ['Aisha', 'TQC-ABC123', 'Kashmir', '20 Oct 2026', "\u{20B9}57,750.00"],
            ],
            'booking_invoice' => [
                'label' => 'Tax Invoice (with Invoice PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, please find your official tax invoice for booking {{2}} attached.\n\nInvoice Date: {{3}}\nAmount Paid: {{4}}\nTrip / Service: {{5}}\n\nThank you for choosing Leemroz Travels! If you have any questions regarding your invoice, feel free to reply to this message.",
                'examples' => ['Aisha', 'TQC-ABC123', '21 Sep 2026', "\u{20B9}57,750.00", 'Kashmir Delight 5D/4N'],
            ],
            'hotel_voucher' => [
                'label' => 'Hotel Voucher (with Voucher PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, your hotel booking {{2}} at {{3}} is confirmed \u{2705}\n\nPlease find your hotel voucher attached.\n\nCheck-in: {{4}}\nAmount paid: {{5}}\n\nThank you for choosing Leemroz Travels! Reply here if you need any help.",
                'examples' => ['Aisha', 'TQC-ABC123', 'The Lalit Grand Palace, Srinagar', '20 Oct 2026', "\u{20B9}57,750.00"],
            ],
            'cab_voucher' => [
                'label' => 'Cab Voucher (with Voucher PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, your cab booking {{2}} is confirmed \u{2705}\n\nPlease find your cab voucher attached.\n\nRoute: {{3}}\nPickup: {{4}}\nAmount paid: {{5}}\n\nThank you for choosing Leemroz Travels! Reply here if you need any help.",
                'examples' => ['Aisha', 'TQC-ABC123', "Srinagar \u{2192} Gulmarg", '20 Oct 2026, 09:00 AM', "\u{20B9}4,500.00"],
            ],
            'quotation_sent' => [
                'label' => 'Quotation Sent',
                'category' => 'UTILITY',
                'header_type' => 'none',
                'body' => "Hi {{1}}, here is your travel quotation {{2}} from Leemroz Travels.\nTotal: {{3}}\nView, accept or decline online: {{4}}\nReply here if you have any questions.",
                'examples' => ['Aisha', 'QT-2026-000001', "\u{20B9}55,000.00", 'https://example.com/quote/token123'],
            ],
            'quotation_sent_pdf' => [
                'label' => 'Quotation Sent (with PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, please find your travel quotation {{2}} from Leemroz Travels attached.\nTotal: {{3}}\nView, accept or decline online: {{4}}\nReply here if you have any questions.",
                'examples' => ['Aisha', 'QT-2026-000001', "\u{20B9}55,000.00", 'https://example.com/quote/token123'],
            ],
            'booking_cancelled' => [
                'label' => 'Booking Cancellation',
                'category' => 'UTILITY',
                'body' => "Hi {{1}}, your booking {{2}} has been cancelled.\nA refund of {{3}} will be processed within {{4}} working days.\nFor help, reply to this message.",
                'examples' => ['Aisha', 'TQC-ABC123', "\u{20B9}57,750.00", '5-7'],
            ],
            'trip_customer_itinerary' => [
                'label' => 'Trip: Customer Itinerary (with Itinerary PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, your itinerary for {{2}} is ready \u{2705}\n\nPlease find your detailed day-by-day itinerary attached.\n\nStart date: {{3}}\nDuration: {{4}}\n\nWe look forward to hosting you. Reply here if you need anything before you travel.",
                'examples' => ['Aisha', 'Kashmir', '20 Oct 2026', '5 day(s)'],
            ],
            'trip_driver_assigned' => [
                'label' => 'Trip: Driver Assigned (with Driver Sheet PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, you have a new assignment for guest {{2}}.\n\nArrival: {{3}}\nDestination: {{4}}\nPickup: {{5}}\n\nYour driver sheet is attached. Reply here if anything is unclear.",
                'examples' => ['Imran', 'Aisha', '20 Oct 2026', 'Srinagar', 'Srinagar Airport'],
            ],
            'trip_driver_sheet' => [
                'label' => 'Trip: Driver Sheet (with Driver Sheet PDF)',
                'category' => 'UTILITY',
                'header_type' => 'document',
                'body' => "Hi {{1}}, please find the driver sheet for guest {{2}} attached.\n\nArrival: {{3}}\nDestination: {{4}}\nPickup: {{5}}\n\nIt has the full day-by-day plan and pickup details. Reply here if anything is unclear.",
                'examples' => ['Imran', 'Aisha', '20 Oct 2026', 'Srinagar', 'Srinagar Airport'],
            ],
            'trip_driver_reminder' => [
                'label' => 'Trip: Driver Reminder',
                'category' => 'UTILITY',
                'header_type' => 'none',
                'body' => "Reminder for {{1}}: pickup for guest {{2}} on {{3}}.\nPickup point: {{4}}.\nReply here if you have any questions.",
                'examples' => ['Imran', 'Aisha', '20 Oct 2026, 09:00 AM', 'Srinagar Airport'],
            ],
            'trip_tomorrow_plan' => [
                'label' => "Trip: Tomorrow's Plan",
                'category' => 'UTILITY',
                'header_type' => 'none',
                'body' => "Hi {{1}}, here's a quick heads-up for your {{2}} trip tomorrow ({{3}}).\nYour driver will reach out with exact pickup timings. Have a wonderful day!",
                'examples' => ['Aisha', 'Kashmir', '21 Oct 2026'],
            ],
        ];

        $preset = $presets[$request->query('preset')] ?? null;

        return view('admin.crm.whatsapp.template_create', compact('presets', 'preset'));
    }

    public function store(Request $request, WhatsAppTemplateService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120|regex:/^[a-z0-9_]+$/',
            'category' => 'required|in:UTILITY,MARKETING,AUTHENTICATION',
            'language' => 'required|string|max:12',
            'header_type' => 'nullable|in:none,document',
            'body' => 'required|string|max:1024',
            'examples' => 'nullable|array',
            'examples.*' => 'nullable|string|max:200',
        ], [
            'name.regex' => 'Template name must be lowercase letters, numbers and underscores only.',
        ]);

        $result = $service->createTemplate(
            $validated['name'],
            $validated['category'],
            $validated['language'],
            $validated['body'],
            array_values($validated['examples'] ?? []),
            $validated['header_type'] ?? 'none',
        );

        if (! $result['success']) {
            return back()->withInput()->with('error', 'Could not create template: ' . $result['error']);
        }

        return redirect()->route('admin.whatsapp-templates.index')
            ->with('success', "Template '{$validated['name']}' submitted to Meta for approval. It will be usable once approved.");
    }
}
