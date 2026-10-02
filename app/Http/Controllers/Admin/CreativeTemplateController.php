<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreativeTemplate;
use App\Services\ActivityLogger;
use App\Services\Marketing\Creative\CreativeFormats;
use Illuminate\Http\Request;

/**
 * Dynamic creative templates. Headline / primary-text / image-prompt fields
 * support {{package_name}}, {{destination}}, {{price}}, {{discount}}, {{hotel_name}},
 * {{phone}}, {{website}}, {{whatsapp}}, {{cta}}, {{booking_url}} tokens.
 */
class CreativeTemplateController extends Controller
{
    public function index()
    {
        return view('admin.marketing.studio.templates', [
            'pageTitle' => 'Creative Templates',
            'templates' => CreativeTemplate::orderBy('category')->orderBy('name')->paginate(24),
            'platforms' => CreativeFormats::PLATFORMS,
            'formats' => CreativeFormats::FORMATS,
            'styles' => CreativeFormats::STYLES,
            'variables' => ['package_name', 'destination', 'duration', 'price', 'discount', 'hotel_name', 'phone', 'website', 'whatsapp', 'cta', 'booking_url'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth('admin')->id();
        $tpl = CreativeTemplate::create($data);
        ActivityLogger::log('create', 'creative_studio', 'Template created: ' . $tpl->name, ['id' => $tpl->id]);

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, CreativeTemplate $template)
    {
        $template->update($this->validated($request));
        ActivityLogger::log('update', 'creative_studio', 'Template updated: ' . $template->name, ['id' => $template->id]);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(CreativeTemplate $template)
    {
        if ($template->is_system) {
            return back()->with('error', 'System templates cannot be deleted.');
        }
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'category' => 'required|in:package,hotel,promotion,social',
            'subcategory' => 'nullable|string|max:60',
            'platform' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::PLATFORMS)),
            'format' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::FORMATS)),
            'style' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::STYLES)),
            'description' => 'nullable|string|max:300',
            'headline_template' => 'nullable|string|max:300',
            'primary_text_template' => 'nullable|string|max:600',
            'image_prompt_template' => 'nullable|string|max:600',
            'status' => 'nullable|in:active,inactive',
        ]);
    }
}
