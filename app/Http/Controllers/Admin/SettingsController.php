<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin settings, presented as a category rail + tabbed panels
 * (structure driven by config/settings_ui.php). Every field in the config
 * persists to the settings table; secrets stay encrypted when left blank.
 */
class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings)
    {
    }

    public function index()
    {
        $categories = config('settings_ui.categories');
        $values = Setting::pluck('value', 'key');

        return view('admin.settings.index', compact('categories', 'values'));
    }

    public function update(Request $request)
    {
        $fields = $this->allFields();

        foreach ($fields as $key => $meta) {
            // only persist fields that belong to the submitted category panel
            if ($request->filled('category') && ($meta['category'] ?? '') !== $request->input('category')) {
                continue;
            }

            $value = $request->input("settings.{$key}");

            if (in_array($meta['type'] ?? 'text', ['toggle'])) {
                $value = $request->boolean("settings.{$key}") ? '1' : '0';
            }

            if (($meta['type'] ?? 'text') === 'password') {
                if ($value === null || $value === '') {
                    continue; // blank keeps the stored secret
                }
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => $meta['category'] ?? 'general',
                    'type' => $meta['type'] ?? 'text',
                    'is_public' => $meta['public'] ?? false,
                ]
            );
        }

        ActivityLogger::log('update', 'settings', 'Settings updated (' . $request->input('category', 'general') . ')');

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Send a diagnostic email using the SMTP credentials stored in settings.
     */
    public function sendTestEmail(Request $request)
    {
        $validated = $request->validate(['test_email' => 'required|email']);

        $result = app(\App\Services\MailConfigService::class)->testConnection($validated['test_email']);

        ActivityLogger::log('update', 'settings', 'Test email to ' . $validated['test_email'] . ': ' . ($result['success'] ? 'sent' : 'failed'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Flatten the settings UI config into key => meta, remembering the category.
     */
    protected function allFields(): array
    {
        $fields = [];

        foreach (config('settings_ui.categories') as $category) {
            foreach ($category['tabs'] as $tab) {
                foreach ($tab['sections'] as $section) {
                    foreach ($section['fields'] as $field) {
                        $fields[$field['key']] = $field + ['category' => $category['key']];
                    }
                }
            }
        }

        return $fields;
    }
}
