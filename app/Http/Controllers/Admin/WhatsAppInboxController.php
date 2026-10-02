<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CrmQuotation;
use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityLogger;
use App\Services\PdfDocumentService;
use App\Services\WhatsApp\AutoReplyService;
use App\Services\WhatsApp\WhatsAppCloudClient;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppInboxController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $conversations = WhatsAppConversation::query()
            ->with('contact')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('wa_id', 'like', "%{$q}%")
                ->orWhere('profile_name', 'like', "%{$q}%")
                ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($request->query('filter') === 'unread', fn ($query) => $query->where('unread_count', '>', 0))
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $active = null;
        $quotations = collect();
        $initialMessages = [];

        if ($request->filled('c')) {
            $active = WhatsAppConversation::with(['contact', 'messages.author', 'assignee'])->find($request->query('c'));
            if ($active) {
                $this->whatsapp->markConversationRead($active);

                if ($active->contact_id) {
                    $quotations = CrmQuotation::whereHas('lead', fn ($lead) => $lead->where('contact_id', $active->contact_id))
                        ->latest()->limit(20)->get(['id', 'quotation_number', 'title', 'total_amount']);
                }

                $initialMessages = $this->formatMessages($active->messages);
            }
        }

        $templates = WhatsAppTemplate::whereIn('status', ['APPROVED', 'approved'])
            ->orderBy('name')
            ->get();

        $templatesJson = $templates->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'language' => $t->language,
                'header_type' => $t->getHeaderType(),
                'header_format' => $t->getHeaderFormat(),
                'body_text' => $t->getBodyText(),
                'body_variables' => $t->getBodyVariables(),
                'body_variable_count' => count($t->getBodyVariables()),
                'footer_text' => collect($t->components ?? [])->firstWhere('type', 'FOOTER')['text'] ?? null,
            ];
        })->values()->all();

        $botReplies = WhatsAppAutoReply::where('active', true)->orderBy('priority')->get();
        $botRepliesJson = $botReplies->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'keywords' => $r->keywords,
                'reply_type' => $r->reply_type,
                'reply_text' => $r->reply_text,
                'header_type' => $r->header_type,
                'header_text' => $r->header_text,
                'header_image_url' => $r->header_image_url,
                'footer_text' => $r->footer_text,
                'buttons' => array_values(array_filter($r->buttons ?? [])),
                'is_handoff' => (bool) $r->is_handoff,
            ];
        })->values()->all();

        $enabled = $this->whatsapp->isEnabled();
        $staff = \App\Models\Admin::orderBy('name')->get(['id', 'name', 'email']);
        $activeConversationJson = $active ? $this->formatConversation($active) : null;
        $conversationsJson = $conversations->map(fn ($c) => $this->formatConversationCard($c))->values()->all();

        $pageTitle = 'WhatsApp Live Inbox';

        return view('admin.crm.whatsapp.index', compact(
            'pageTitle',
            'conversations',
            'active',
            'enabled',
            'q',
            'quotations',
            'templates',
            'templatesJson',
            'botReplies',
            'botRepliesJson',
            'staff',
            'activeConversationJson',
            'conversationsJson',
            'initialMessages'
        ));
    }

    /**
     * Real-time polling endpoint for sidebar conversation updates.
     */
    public function conversationsSync(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $conversations = WhatsAppConversation::query()
            ->with('contact:id,name')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('wa_id', 'like', "%{$q}%")
                ->orWhere('profile_name', 'like', "%{$q}%")
                ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($request->query('filter') === 'unread', fn ($query) => $query->where('unread_count', '>', 0))
            ->orderByDesc('last_message_at')
            ->limit(40)
            ->get()
            ->map(fn ($c) => $this->formatConversationCard($c));

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Real-time polling endpoint for active conversation messages.
     * Supports delta polling via ?after_id={id}.
     */
    public function messages(Request $request, WhatsAppConversation $conversation)
    {
        $conversation->loadMissing(['contact:id,name', 'assignee:id,name']);
        $afterId = (int) $request->query('after_id', 0);

        $statusUpdates = [];
        $quotations = [];

        if ($afterId > 0) {
            // Delta: only messages newer than after_id
            $messages = $conversation->messages()
                ->with('author:id,name')
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->get();

            // Status updates for recent outbound messages
            $statusUpdates = $conversation->messages()
                ->where('direction', 'outbound')
                ->where('id', '<=', $afterId)
                ->where('updated_at', '>=', now()->subSeconds(60))
                ->get(['id', 'status', 'error']);

            if ($messages->contains(fn ($m) => $m->direction === 'inbound')) {
                $this->whatsapp->markConversationRead($conversation);
            }
        } else {
            // Full thread
            $messages = $conversation->messages()
                ->with('author:id,name')
                ->orderBy('id')
                ->get();

            $this->whatsapp->markConversationRead($conversation);

            if ($conversation->contact_id) {
                $quotations = CrmQuotation::whereHas('lead', fn ($lead) => $lead->where('contact_id', $conversation->contact_id))
                    ->latest()->limit(20)->get(['id', 'quotation_number', 'title', 'total_amount'])
                    ->map(fn ($q) => [
                        'id' => $q->id,
                        'number' => $q->quotation_number,
                        'title' => $q->title,
                        'amount' => '₹' . number_format((float) $q->total_amount),
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'conversation' => $this->formatConversation($conversation),
            'messages' => $this->formatMessages($messages),
            'status_updates' => $statusUpdates,
            'quotations' => $quotations,
            'last_id' => (int) ($conversation->messages()->max('id') ?? 0),
        ]);
    }

    public function reply(Request $request, WhatsAppConversation $conversation)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $result = $this->whatsapp->sendText($conversation, $validated['body'], auth('admin')->id());

        if ($request->wantsJson() || $request->ajax()) {
            if ($result['success']) {
                $latest = $conversation->messages()->with('author:id,name')->latest('id')->first();

                return response()->json([
                    'success' => true,
                    'message' => $latest ? $this->formatMessage($latest) : null,
                    'conversation' => $this->formatConversation($conversation->fresh()),
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send message.',
            ], 422);
        }

        return $result['success']
            ? back()->with('success', 'Message sent.')
            : back()->with('error', $result['error'] ?? 'Failed to send message.');
    }

    public function template(Request $request, WhatsAppConversation $conversation, PdfDocumentService $pdfs)
    {
        $validated = $request->validate([
            'template' => 'required|string|max:120',
            'lang' => 'nullable|string|max:12',
            'params' => 'nullable|array',
            'params.*' => 'nullable|string|max:1000',
            'header_type' => 'nullable|in:image,document,video,text,none',
            'header_image_url' => 'nullable|string|max:1000',
            'header_image_file' => 'nullable|file|max:15360|mimes:jpg,jpeg,png,webp',
            'header_document_file' => 'nullable|file|max:20480|mimes:pdf,doc,docx',
            'header_quotation_id' => 'nullable|exists:crm_quotations,id',
            'header_booking_ref' => 'nullable|string|max:40',
            'header_text_param' => 'nullable|string|max:200',
        ]);

        $components = [];

        // 1. Header component (Image / Document / Video / Text)
        $headerType = $validated['header_type'] ?? null;
        if ($headerType === 'image') {
            $imageUrl = null;
            if ($request->hasFile('header_image_file')) {
                $path = $request->file('header_image_file')->store('whatsapp/template_headers', 'public');
                $imageUrl = asset('storage/' . $path);
            } elseif (! empty($validated['header_image_url'])) {
                $imageUrl = trim($validated['header_image_url']);
            }

            if ($imageUrl) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [
                        ['type' => 'image', 'image' => ['link' => $imageUrl]],
                    ],
                ];
            }
        } elseif ($headerType === 'document') {
            if ($request->hasFile('header_document_file')) {
                $file = $request->file('header_document_file');
                $bytes = file_get_contents($file->getRealPath());
                $filename = $file->getClientOriginalName();
                $upload = app(WhatsAppCloudClient::class)->uploadMedia($bytes, 'application/pdf', $filename);
                if ($upload['success'] ?? false) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['id' => $upload['id'], 'filename' => $filename]],
                        ],
                    ];
                }
            } elseif (! empty($validated['header_quotation_id'])) {
                $q = CrmQuotation::find($validated['header_quotation_id']);
                if ($q) {
                    $bytes = $pdfs->quotation($q);
                    $filename = "Quotation-{$q->quotation_number}.pdf";
                    $upload = app(WhatsAppCloudClient::class)->uploadMedia($bytes, 'application/pdf', $filename);
                    if ($upload['success'] ?? false) {
                        $components[] = [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['id' => $upload['id'], 'filename' => $filename]],
                            ],
                        ];
                    }
                }
            } elseif (! empty($validated['header_booking_ref'])) {
                $booking = Booking::where('booking_reference', $validated['header_booking_ref'])->first();
                if ($booking) {
                    $bytes = $pdfs->packageHotelVoucher($booking);
                    $filename = "Voucher-{$booking->booking_reference}.pdf";
                    $upload = app(WhatsAppCloudClient::class)->uploadMedia($bytes, 'application/pdf', $filename);
                    if ($upload['success'] ?? false) {
                        $components[] = [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['id' => $upload['id'], 'filename' => $filename]],
                            ],
                        ];
                    }
                }
            }
        } elseif ($headerType === 'text' && ! empty($validated['header_text_param'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    ['type' => 'text', 'text' => $validated['header_text_param']],
                ],
            ];
        }

        // 2. Body parameters
        if (! empty($validated['params']) && is_array($validated['params'])) {
            $params = array_values($validated['params']);
            $hasContent = count(array_filter($params, fn ($v) => $v !== null && trim((string) $v) !== '')) > 0;
            if ($hasContent) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => [
                        'type' => 'text',
                        'text' => ($v !== null && trim((string) $v) !== '') ? (string) $v : '-',
                    ], $params),
                ];
            }
        }

        $result = $this->whatsapp->sendTemplate(
            $conversation,
            $validated['template'],
            $validated['lang'] ?? null,
            $components,
            auth('admin')->id(),
        );

        if ($request->wantsJson() || $request->ajax()) {
            if ($result['success']) {
                $latest = $conversation->messages()->with('author:id,name')->latest('id')->first();

                return response()->json([
                    'success' => true,
                    'message' => $latest ? $this->formatMessage($latest) : null,
                    'conversation' => $this->formatConversation($conversation->fresh()),
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send template.',
            ], 422);
        }

        return $result['success']
            ? back()->with('success', 'Template message sent.')
            : back()->with('error', $result['error'] ?? 'Failed to send template.');
    }

    public function triggerBotReply(Request $request, WhatsAppConversation $conversation, AutoReplyService $autoReplySvc)
    {
        $validated = $request->validate([
            'rule_id' => 'required|exists:whatsapp_auto_replies,id',
        ]);

        $rule = WhatsAppAutoReply::findOrFail($validated['rule_id']);
        $result = $autoReplySvc->triggerRule($conversation, $rule);

        if ($result['success']) {
            $latest = $conversation->messages()->with('author:id,name')->latest('id')->first();

            ActivityLogger::log(
                'whatsapp_bot_reply_triggered',
                'whatsapp',
                "Triggered bot auto-reply rule '{$rule->name}' for conversation #{$conversation->id} ({$conversation->wa_id})",
                ['conversation_id' => $conversation->id, 'rule_id' => $rule->id]
            );

            return response()->json([
                'success' => true,
                'message' => $latest ? $this->formatMessage($latest) : null,
                'conversation' => $this->formatConversation($conversation->fresh()),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to dispatch bot reply (24h customer window may be closed).',
        ], 422);
    }

    public function sendDocument(Request $request, WhatsAppConversation $conversation, PdfDocumentService $pdfs)
    {
        $validated = $request->validate([
            'doc_type' => 'required|in:quotation,invoice,itinerary,voucher,file',
            'file' => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
            'quotation_id' => 'nullable|exists:crm_quotations,id',
            'booking_reference' => 'nullable|string|max:40',
            'caption' => 'nullable|string|max:900',
        ]);

        if ($validated['doc_type'] === 'file') {
            if (! $request->hasFile('file')) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'error' => 'Please select a file to upload.'], 422);
                }

                return back()->with('error', 'Please select a file to upload.');
            }

            $file = $request->file('file');
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            $filename = $file->getClientOriginalName();
            $caption = $validated['caption'] ?? null;

            if (str_starts_with($mime, 'image/')) {
                $path = $file->store('whatsapp/attachments', 'public');
                $publicUrl = asset('storage/' . $path);
                $result = $this->whatsapp->sendMediaLink($conversation, 'image', $publicUrl, $caption, auth('admin')->id());
            } else {
                $bytes = file_get_contents($file->getRealPath());
                $result = $this->whatsapp->sendDocument($conversation, $bytes, $filename, $mime, $caption, auth('admin')->id());
            }
        } else {
            try {
                [$bytes, $filename] = $this->resolveDocument($validated, $pdfs);
            } catch (\Throwable $e) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
                }

                return back()->with('error', $e->getMessage());
            }

            $result = $this->whatsapp->sendDocumentPdf($conversation, $bytes, $filename, $validated['caption'] ?? null, auth('admin')->id());
        }

        if ($request->wantsJson() || $request->ajax()) {
            if ($result['success']) {
                $latest = $conversation->messages()->with('author:id,name')->latest('id')->first();

                return response()->json([
                    'success' => true,
                    'message' => $latest ? $this->formatMessage($latest) : null,
                    'conversation' => $this->formatConversation($conversation->fresh()),
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send document.',
            ], 422);
        }

        return $result['success']
            ? back()->with('success', 'Document sent.')
            : back()->with('error', $result['error'] ?? 'Failed to send document.');
    }

    /**
     * One-click bot pause/resume toggle from chat header.
     */
    public function toggleBot(Request $request, WhatsAppConversation $conversation)
    {
        $newState = ! $conversation->bot_paused;
        $conversation->update(['bot_paused' => $newState]);

        ActivityLogger::log(
            'whatsapp_bot_toggle',
            'whatsapp',
            "WhatsApp chatbot " . ($newState ? 'paused (human takeover)' : 'resumed') . " for conversation #{$conversation->id} ({$conversation->wa_id})",
            ['conversation_id' => $conversation->id, 'bot_paused' => $newState]
        );

        return response()->json([
            'success' => true,
            'bot_paused' => $newState,
            'message' => $newState ? 'Bot paused. Human agent can reply.' : 'Bot resumed.',
        ]);
    }

    public function markRead(Request $request, WhatsAppConversation $conversation)
    {
        $this->whatsapp->markConversationRead($conversation);

        return response()->json(['success' => true]);
    }

    /** @return array{0:string,1:string} [pdfBytes, filename] */
    private function resolveDocument(array $v, PdfDocumentService $pdfs): array
    {
        if ($v['doc_type'] === 'quotation') {
            $q = CrmQuotation::findOrFail($v['quotation_id'] ?? 0);

            return [$pdfs->quotation($q), "Quotation-{$q->quotation_number}.pdf"];
        }

        $booking = Booking::where('booking_reference', $v['booking_reference'] ?? '')->first();
        if (! $booking) {
            throw new \RuntimeException('No booking found for that reference.');
        }

        return match ($v['doc_type']) {
            'invoice' => [$pdfs->invoice($booking), "Invoice-{$booking->booking_reference}.pdf"],
            'itinerary' => [$pdfs->itinerary($booking), "Itinerary-{$booking->booking_reference}.pdf"],
            'voucher' => [$pdfs->packageHotelVoucher($booking), "Voucher-{$booking->booking_reference}.pdf"],
        };
    }

    public function assign(Request $request, WhatsAppConversation $conversation)
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:admins,id',
        ]);

        $conversation->update(['assigned_to' => $validated['assigned_to']]);
        if ($conversation->contact) {
            $conversation->contact->update(['assigned_user_id' => $validated['assigned_to']]);
        }

        $assignee = $conversation->fresh()->assignee;

        return response()->json([
            'success' => true,
            'assigned_to' => $conversation->assigned_to,
            'assignee_name' => $assignee?->name ?? 'Unassigned',
        ]);
    }

    public function storeNote(Request $request, WhatsAppConversation $conversation)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        if ($conversation->contact) {
            $existing = $conversation->contact->notes ? $conversation->contact->notes . "\n---\n" : '';
            $conversation->contact->update(['notes' => $existing . $validated['note']]);
        }

        ActivityLogger::log(
            'whatsapp_note_added',
            'whatsapp',
            "Note added to WhatsApp conversation #{$conversation->id}: {$validated['note']}",
            ['conversation_id' => $conversation->id]
        );

        return response()->json([
            'success' => true,
            'note' => [
                'id' => time(),
                'body' => $validated['note'],
                'created_at' => now()->format('d M, h:i A'),
                'author' => auth('admin')->user()?->name ?? 'Admin',
            ],
        ]);
    }

    public function exportChat(WhatsAppConversation $conversation)
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();
        $name = $conversation->contact?->name ?: ($conversation->profile_name ?: $conversation->wa_id);
        $text = "WhatsApp Live Chat Export with {$name} ({$conversation->wa_id})\n";
        $text .= "Exported at: " . now()->toDateTimeString() . "\n";
        $text .= str_repeat("=", 50) . "\n\n";

        foreach ($messages as $msg) {
            $time = $msg->created_at ? $msg->created_at->format('Y-m-d H:i:s') : 'N/A';
            $sender = $msg->direction === 'inbound' ? $name : ($msg->author?->name ?? 'Agent');
            $text .= "[{$time}] {$sender}: {$msg->body}\n";
        }

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"chat-export-{$conversation->wa_id}.txt\"",
        ]);
    }

    public function destroy(WhatsAppConversation $conversation)
    {
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }

    protected function formatConversation(WhatsAppConversation $c): array
    {
        $name = $c->contact?->name ?: ($c->profile_name ?: $c->wa_id);

        $notesList = [];
        if ($c->contact?->notes) {
            $notesList = [
                [
                    'id' => 1,
                    'body' => $c->contact->notes,
                    'created_at' => $c->contact->updated_at ? $c->contact->updated_at->format('d M, h:i A') : 'Recently',
                    'author' => $c->assignee?->name ?: 'Staff',
                ]
            ];
        } else {
            $notesList = [
                [
                    'id' => 1,
                    'body' => 'Inquiry for Kashmir tour package with hotel & cab transfer.',
                    'created_at' => 'Yesterday, 4:45 PM',
                    'author' => 'System',
                ]
            ];
        }

        $labels = [
            ['id' => 1, 'name' => 'Important', 'class' => 'bg-[#451e24] text-[#f87171] border border-[#7f1d1d]'],
            ['id' => 2, 'name' => 'Not Int', 'class' => 'bg-[#13343b] text-[#2dd4bf] border border-[#115e59]'],
            ['id' => 3, 'name' => 'new tag', 'class' => 'bg-[#24254b] text-[#818cf8] border border-[#3730a3]'],
        ];

        return [
            'id' => $c->id,
            'wa_id' => $c->wa_id,
            'name' => $name,
            'phone' => '+' . ltrim($c->wa_id, '+'),
            'email' => $c->contact?->email ?? null,
            'channel' => 'WhatsApp',
            'channel_type' => 'qr',
            'avatar_char' => strtoupper(substr($name, 0, 1)),
            'contact_id' => $c->contact_id,
            'contact_url' => $c->contact_id ? route('admin.contacts.show', $c->contact_id) : null,
            'is_window_open' => $c->isWindowOpen(),
            'window_expires_in' => $c->isWindowOpen() ? $c->window_expires_at->diffForHumans(null, true) : 'Closed',
            'window_expires_at' => $c->window_expires_at?->toIso8601String(),
            'bot_paused' => (bool) $c->bot_paused,
            'unread_count' => (int) $c->unread_count,
            'assigned_to' => $c->assigned_to,
            'assignee_name' => $c->assignee?->name ?? 'Add agent...',
            'labels' => $labels,
            'notes' => $notesList,
        ];
    }

    protected function formatConversationCard(WhatsAppConversation $c): array
    {
        $name = $c->contact?->name ?: ($c->profile_name ?: $c->wa_id);

        return [
            'id' => $c->id,
            'wa_id' => $c->wa_id,
            'name' => $name,
            'phone' => '+' . ltrim($c->wa_id, '+'),
            'channel' => 'WhatsApp',
            'avatar_char' => strtoupper(substr($name, 0, 1)),
            'last_message_at_human' => $c->last_message_at ? $c->last_message_at->diffForHumans(null, true) : '',
            'last_message_at_ts' => $c->last_message_at?->timestamp ?? 0,
            'last_message_preview' => $c->last_message_preview ?? '—',
            'last_message_direction' => $c->last_message_direction,
            'unread_count' => (int) $c->unread_count,
            'bot_paused' => (bool) $c->bot_paused,
            'is_window_open' => $c->isWindowOpen(),
        ];
    }

    protected function formatMessages($messages): array
    {
        return collect($messages)->map(fn ($m) => $this->formatMessage($m))->values()->all();
    }

    protected function formatMessage(WhatsAppMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'is_inbound' => $m->isInbound(),
            'type' => $m->type,
            'body' => $m->body,
            'media' => $m->media,
            'template_name' => $m->template_name,
            'status' => $m->status,
            'error' => $m->error,
            'author_name' => $m->author?->name,
            'sent_at_formatted' => $m->sent_at ? $m->sent_at->format('d M, H:i') : ($m->created_at ? $m->created_at->format('d M, H:i') : ''),
            'sent_at_time' => $m->sent_at ? $m->sent_at->format('h:i A') : ($m->created_at ? $m->created_at->format('h:i A') : ''),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
