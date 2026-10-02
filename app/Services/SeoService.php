<?php

namespace App\Services;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves admin-managed SEO per page key or entity with sensible fallbacks.
 */
class SeoService
{
    public function __construct(protected SettingsService $settings)
    {
    }

    public function forPage(string $pageKey, ?Model $entity = null, array $defaults = []): array
    {
        $query = SeoMetadata::query()
            ->where(fn ($q) => $q->where('page_key', $pageKey)->orWhereNull('page_key'))
            ->orderByRaw('page_key IS NULL');

        if ($entity) {
            $query->where(function ($q) use ($entity) {
                $q->where(fn ($qq) => $qq->where('entity_type', class_basename($entity))->where('entity_id', $entity->id))
                    ->orWhereNull('entity_id');
            });
        } else {
            $query->whereNull('entity_id');
        }

        $seo = $query->first();

        $siteName = $this->settings->company('name', config('app.name'));

        return [
            'title' => $seo?->seo_title ?? $defaults['title'] ?? $siteName,
            'description' => $seo?->meta_description ?? $defaults['description'] ?? $this->settings->get('seo_meta_description', ''),
            'keywords' => $seo?->meta_keywords ?? '',
            'canonical' => $seo?->canonical_url ?? $defaults['canonical'] ?? url()->current(),
            'og_title' => $seo?->og_title ?? $seo?->seo_title ?? $defaults['title'] ?? $siteName,
            'og_description' => $seo?->og_description ?? $seo?->meta_description ?? $defaults['description'] ?? '',
            'og_image' => $seo?->og_image ?? $defaults['og_image'] ?? $this->settings->get('og_default_image'),
            'schema' => $seo?->schema_json,
            'robots' => $seo?->robots ?? 'index,follow',
        ];
    }
}
