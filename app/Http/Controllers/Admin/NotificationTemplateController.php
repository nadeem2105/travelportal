<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index()
    {
        $templates = NotificationTemplate::orderBy('channel')->orderBy('key')->get();

        return view('admin.notifications.templates', compact('templates'));
    }

    public function update(Request $request, NotificationTemplate $template)
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'subject' => $validated['subject'] ?? $template->subject,
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLogger::log('update', 'notifications', "Updated template {$template->key}");

        return back()->with('success', 'Template updated.');
    }
}
