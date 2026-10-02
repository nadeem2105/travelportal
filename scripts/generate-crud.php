<?php

/**
 * Generates standard admin CRUD controllers for simple catalog/content
 * entities. Each controller: index + create + store + edit + update +
 * destroy, with validation rules from the field map.
 * Run: php scripts/generate-crud.php
 */

$entities = [
    'Vehicle' => [
        'model' => 'App\\Models\\Vehicle',
        'table' => 'vehicles',
        'views' => 'vehicles',
        'with' => ['vendor', 'type'],
        'fields' => [
            'name' => 'required|string|max:100',
            'vendor_id' => 'nullable|integer|exists:cab_vendors,id',
            'vehicle_type_id' => 'required|integer|exists:vehicle_types,id',
            'image' => 'nullable|string|max:255',
            'passenger_capacity' => 'required|integer|min:1|max:50',
            'luggage_capacity' => 'required|integer|min:0|max:20',
            'base_price' => 'required|numeric|min:0',
            'per_km_rate' => 'required|numeric|min:0',
            'per_hour_rate' => 'nullable|numeric|min:0',
            'cancellation_policy' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive',
        ],
        'booleans' => ['is_ac', 'is_featured'],
    ],
    'VehicleType' => [
        'model' => 'App\\Models\\VehicleType',
        'table' => 'vehicle_types',
        'views' => 'vehicle_types',
        'fields' => [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ],
    ],
    'Vendor' => [
        'model' => 'App\\Models\\CabVendor',
        'table' => 'cab_vendors',
        'views' => 'vendors',
        'fields' => [
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
        ],
    ],
    'CabLocation' => [
        'model' => 'App\\Models\\CabLocation',
        'table' => 'cab_locations',
        'views' => 'cab_locations',
        'fields' => [
            'name' => 'required|string|max:100',
            'city' => 'nullable|string|max:80',
            'type' => 'required|in:airport,local,outstation',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ],
    ],
    'Airport' => [
        'model' => 'App\\Models\\Airport',
        'table' => 'airports',
        'views' => 'airports',
        'fields' => [
            'code' => 'required|string|size:3|unique:airports,code',
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:80',
            'country' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ],
        'booleans' => ['is_popular'],
    ],
    'Airline' => [
        'model' => 'App\\Models\\Airline',
        'table' => 'airlines',
        'views' => 'airlines',
        'fields' => [
            'code' => 'required|string|size:2|unique:airlines,code',
            'name' => 'required|string|max:100',
            'default_baggage_kg' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ],
        'booleans' => ['is_lcc'],
    ],
    'Coupon' => [
        'model' => 'App\\Models\\Coupon',
        'table' => 'coupons',
        'views' => 'coupons',
        'fields' => [
            'code' => 'required|string|max:40|unique:coupons,code',
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'product_types' => 'nullable|array',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => 'required|in:active,inactive',
        ],
        'booleans' => ['first_booking_only'],
    ],
    'Offer' => [
        'model' => 'App\\Models\\Offer',
        'table' => 'offers',
        'views' => 'offers',
        'fields' => [
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|string|max:255',
            'discount_text' => 'nullable|string|max:30',
            'badge' => 'nullable|string|max:50',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'link_url' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:50',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ],
    ],
    'Banner' => [
        'model' => 'App\\Models\\Banner',
        'table' => 'banners',
        'views' => 'banners',
        'fields' => [
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'link_url' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:50',
            'position' => 'required|in:home,flights,hotels,cabs,packages,offers',
            'sort_order' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => 'required|in:active,inactive',
        ],
    ],
    'Faq' => [
        'model' => 'App\\Models\\Faq',
        'table' => 'faqs',
        'views' => 'faqs',
        'fields' => [
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'category' => 'required|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ],
    ],
    'Testimonial' => [
        'model' => 'App\\Models\\Testimonial',
        'table' => 'testimonials',
        'views' => 'testimonials',
        'fields' => [
            'customer_name' => 'required|string|max:100',
            'customer_photo' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'destination' => 'nullable|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string|max:1000',
            'status' => 'required|in:active,inactive',
        ],
        'booleans' => ['is_featured'],
    ],
    'Blog' => [
        'model' => 'App\\Models\\Blog',
        'table' => 'blogs',
        'views' => 'blogs',
        'fields' => [
            'title' => 'required|string|max:200',
            'category' => 'required|string|max:60',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'cover_image' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:255',
            'published_at' => 'nullable|date',
            'status' => 'required|in:draft,published,scheduled,archived',
        ],
        'transforms' => [
            'tags' => 'array_map("trim", explode(",", $validated[\"tags\"] ?? [])) ?: null',
            'slug' => 'Str::slug($validated[\"title\"]) . (Blog::where(\"slug\", Str::slug($validated[\"title\"]))->exists() && !$model?->exists ? \"-\" . substr(uniqid(), -4) : \"\")',
        ],
    ],
    'Guide' => [
        'model' => 'App\\Models\\Guide',
        'table' => 'guides',
        'views' => 'guides',
        'fields' => [
            'title' => 'required|string|max:200',
            'destination_id' => 'nullable|integer|exists:destinations,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'cover_image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ],
        'transforms' => [
            'slug' => 'Str::slug($validated[\"title\"]) . (Guide::where(\"slug\", Str::slug($validated[\"title\"]))->exists() && !$model?->exists ? \"-\" . substr(uniqid(), -4) : \"\")',
        ],
    ],
    'Page' => [
        'model' => 'App\\Models\\Page',
        'table' => 'pages',
        'views' => 'pages',
        'fields' => [
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'template' => 'required|in:default,full_width,sidebar',
            'sort_order' => 'nullable|integer|min:0',
            'published_at' => 'nullable|date',
            'status' => 'required|in:draft,published',
        ],
        'booleans' => ['show_in_footer'],
        'transforms' => [
            'slug' => 'Str::slug($validated[\"title\"]) . (Page::where(\"slug\", Str::slug($validated[\"title\"]))->exists() && !$model?->exists ? \"-\" . substr(uniqid(), -4) : \"\")',
        ],
    ],
];

$stub = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use {MODEL};
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class {CLASS}Controller extends Controller
{
    public function index()
    {
        ${VARIABLE}s = {MODEL}::{WITH}latest()->paginate(20);

        return view('admin.{VIEWS}.index', compact('{VARIABLE}s'));
    }

    public function create()
    {
        {EXTRA_DATA}return view('admin.{VIEWS}.form', ['{VARIABLE}' => new {MODEL}(){EXTRA_PASS}]);
    }

    public function store(Request $request)
    {
        {MODEL}::create($this->validated($request, null));

        ActivityLogger::log('create', '{MODULE}', 'Created {MODULE} entry');

        return redirect()->route('admin.{ROUTE}.index')->with('success', '{LABEL} created.');
    }

    public function edit({MODEL} ${VARIABLE})
    {
        {EXTRA_DATA}return view('admin.{VIEWS}.form', ['{VARIABLE}' => ${VARIABLE}{EXTRA_PASS}]);
    }

    public function update(Request $request, {MODEL} ${VARIABLE})
    {
        ${VARIABLE}->update($this->validated($request, ${VARIABLE}));

        ActivityLogger::log('update', '{MODULE}', 'Updated {MODULE} entry #' . ${VARIABLE}->id);

        return back()->with('success', '{LABEL} updated.');
    }

    public function destroy({MODEL} ${VARIABLE})
    {
        ActivityLogger::log('delete', '{MODULE}', 'Deleted {MODULE} entry #' . ${VARIABLE}->id);
        ${VARIABLE}->delete();

        return back()->with('success', '{LABEL} deleted.');
    }

    protected function validated(Request $request, ?{MODEL} $model): array
    {
        $validated = $request->validate([
{RULES}
        ]);
{BOOLEANS}{TRANSFORMS}
        return $validated;
    }
}
PHP;

foreach ($entities as $class => $config) {
    $model = $config['model'];
    $variable = lcfirst($class);
    $module = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));

    $rules = '';
    foreach ($config['fields'] as $field => $rule) {
        $rules .= "            '$field' => '$rule',\n";
    }

    $booleans = '';
    foreach ($config['booleans'] ?? [] as $bool) {
        $booleans .= "        \$validated['$bool'] = \$request->boolean('$bool');\n";
    }

    $transforms = '';
    foreach ($config['transforms'] ?? [] as $field => $expr) {
        $transforms .= "        \$validated['$field'] = $expr;\n";
    }

    $with = ! empty($config['with'])
        ? 'with(' . implode(', ', array_map(fn ($r) => "'" . $r . "'", $config['with'])) . ')->'
        : '';

    $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $class);

    $code = str_replace([
        '{MODEL}', '{CLASS}', '{VARIABLE}', '{VIEWS}', '{RULES}', '{BOOLEANS}', '{TRANSFORMS}', '{MODULE}', '{ROUTE}', '{LABEL}', '{WITH}', '{EXTRA_DATA}', '{EXTRA_PASS}',
    ], [
        $model, $class, $variable, $config['views'], rtrim($rules), $booleans, $transforms, $module, $config['views'], $label, $with, '', '',
    ], $stub);

    file_put_contents(__DIR__ . "/../app/Http/Controllers/Admin/{$class}Controller.php", $code);
    echo "Generated {$class}Controller\n";
}

echo "Done.\n";
