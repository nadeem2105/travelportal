<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WhatsAppAutoReplyController extends Controller
{
    public function index()
    {
        $rules = WhatsAppAutoReply::orderBy('priority')->orderBy('id')->get();

        // Pull full component data so the form can render each template's real
        // shape: how many body variables it needs, and whether it carries a
        // media header (document / image / video) that requires a link.
        $templates = WhatsAppTemplate::whereIn('status', ['APPROVED', 'approved'])
            ->orderBy('name')
            ->get(['id', 'name', 'language', 'category', 'status', 'components', 'body_preview', 'body_variable_count'])
            ->map(function (WhatsAppTemplate $t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'language' => $t->language,
                    'category' => $t->category,
                    'status' => $t->status,
                    'body_preview' => $t->body_preview,
                    'body_variable_count' => (int) $t->body_variable_count,
                    // 'document' | 'image' | 'video' | 'text' | null
                    'header_type' => $t->getHeaderType(),
                    // Raw Meta format (DOCUMENT / IMAGE / VIDEO / TEXT)
                    'header_format' => $t->getHeaderFormat(),
                    // [{index, placeholder, example}, ...]
                    'variables' => $t->getBodyVariables(),
                ];
            })
            ->values();

        return view('admin.crm.auto_replies.index', compact('rules', 'templates'));
    }

    public function store(Request $request)
    {
        WhatsAppAutoReply::create($this->validated($request) + ['created_by' => auth('admin')->id()]);

        return back()->with('success', 'Auto-reply rule created successfully.');
    }

    public function update(Request $request, WhatsAppAutoReply $autoReply)
    {
        $autoReply->update($this->validated($request));

        return back()->with('success', 'Auto-reply rule updated successfully.');
    }

    public function toggle(WhatsAppAutoReply $autoReply)
    {
        $autoReply->update(['active' => ! $autoReply->active]);

        return back()->with('success', 'Rule ' . ($autoReply->active ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(WhatsAppAutoReply $autoReply)
    {
        $autoReply->delete();

        return back()->with('success', 'Auto-reply rule deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'match_type' => 'required|in:exact,contains,starts_with',
            'keywords_raw' => 'nullable|string|max:1000',
            'reply_type' => 'required|in:text,buttons,list,template',
            'reply_text' => 'nullable|required_if:reply_type,text,buttons,list|string|max:4000',
            'header_type' => 'nullable|in:none,text,image',
            'header_text' => 'nullable|string|max:60',
            'header_image_url' => 'nullable|string|max:1000',
            'header_image_file' => 'nullable|image|max:5120',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array|max:3',
            'buttons.*' => 'nullable|string|max:20',
            'buttons_raw' => 'nullable|string|max:500',
            'template_name' => 'nullable|required_if:reply_type,template|string|max:150',
            'template_language' => 'nullable|string|max:12',
            'template_params' => 'nullable|array|max:20',
            'template_params.*' => 'nullable|string|max:1000',
            'template_header_media_url' => 'nullable|string|max:1000',
            'template_header_media_file' => 'nullable|file|max:16384|mimes:pdf,doc,docx,jpg,jpeg,png,webp,mp4,3gp',
            'is_default' => 'nullable|boolean',
            'is_handoff' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:9999',
        ]);

        $isDefault = $request->boolean('is_default');

        // Comma/newline separated keywords → array.
        $keywords = collect(preg_split('/[\n,]+/', (string) ($data['keywords_raw'] ?? '')))
            ->map(fn ($k) => trim($k))->filter()->values()->all();

        if (! $isDefault && empty($keywords)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'keywords_raw' => 'Please enter at least one keyword, or mark this rule as a default fallback.',
            ]);
        }

        // Header image resolution: uploaded file takes precedence, else public URL.
        $headerImageUrl = $data['header_image_url'] ?? null;
        if ($request->hasFile('header_image_file')) {
            $path = $request->file('header_image_file')->store('whatsapp/headers', 'public');
            $headerImageUrl = Storage::disk('public')->url($path);
        }

        $headerType = $data['header_type'] ?? null;
        if (! in_array($headerType, ['none', 'text', 'image'], true)) {
            if (! empty($headerImageUrl)) {
                $headerType = 'image';
            } elseif (! empty($data['header_text'])) {
                $headerType = 'text';
            } else {
                $headerType = 'none';
            }
        }

        // Parse interactive buttons (up to 3, max 20 chars each)
        $buttons = [];
        if (! empty($data['buttons']) && is_array($data['buttons'])) {
            $buttons = array_values(array_filter(array_map(fn ($b) => mb_substr(trim(is_array($b) ? ($b['title'] ?? '') : (string) $b), 0, 20), $data['buttons'])));
        } elseif (! empty($data['buttons_raw'])) {
            $buttons = collect(preg_split('/[\n,]+/', (string) $data['buttons_raw']))
                ->map(fn ($b) => mb_substr(trim($b), 0, 20))->filter()->values()->all();
        }
        $buttons = array_slice($buttons, 0, 3);

        $replyType = $data['reply_type'];
        if ($replyType !== 'template' && ! empty($buttons)) {
            $replyType = 'buttons';
        }

        // Template body variables: trim, keep order, drop trailing blanks.
        $templateParams = [];
        if ($replyType === 'template' && ! empty($data['template_params']) && is_array($data['template_params'])) {
            $templateParams = array_map(fn ($v) => trim((string) $v), $data['template_params']);
            // Trim trailing empties so we don't send blank variables Meta would reject.
            while (! empty($templateParams) && end($templateParams) === '') {
                array_pop($templateParams);
            }
        }

        $templateHeaderMediaUrl = ($replyType === 'template' && ! empty($data['template_header_media_url']))
            ? trim((string) $data['template_header_media_url'])
            : null;

        // Uploaded header file takes precedence over a pasted link, mirroring the
        // custom-message image header. Stored on the public disk and served by URL
        // so Meta can fetch it when the template is sent.
        if ($replyType === 'template' && $request->hasFile('template_header_media_file')) {
            $path = $request->file('template_header_media_file')->store('whatsapp/headers', 'public');
            $templateHeaderMediaUrl = Storage::disk('public')->url($path);
        }

        return [
            'name' => $data['name'],
            'match_type' => $data['match_type'],
            'keywords' => $keywords ?: null,
            'reply_type' => $replyType,
            'reply_text' => $replyType !== 'template' ? ($data['reply_text'] ?? null) : null,
            'header_type' => $replyType !== 'template' ? $headerType : 'text',
            'header_image_url' => ($replyType !== 'template' && $headerType === 'image') ? $headerImageUrl : null,
            'header_text' => ($replyType !== 'template' && $headerType === 'text') ? (! empty($data['header_text']) ? mb_substr(trim($data['header_text']), 0, 60) : null) : null,
            'footer_text' => $replyType !== 'template' ? (! empty($data['footer_text']) ? mb_substr(trim($data['footer_text']), 0, 60) : null) : null,
            'buttons' => ! empty($buttons) ? $buttons : null,
            'template_name' => $replyType === 'template' ? ($data['template_name'] ?? null) : null,
            'template_language' => ! empty($data['template_language']) ? $data['template_language'] : 'en_US',
            'template_params' => ($replyType === 'template' && ! empty($templateParams)) ? $templateParams : null,
            'template_header_media_url' => $templateHeaderMediaUrl,
            'is_default' => $isDefault,
            'is_handoff' => $request->boolean('is_handoff'),
            'active' => $request->boolean('active', true),
            'priority' => (int) ($data['priority'] ?? 100),
        ];
    }
}
