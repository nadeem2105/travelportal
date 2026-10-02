<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMetadata extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'page_key', 'seo_title', 'meta_description',
        'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image',
        'schema_json', 'robots',
    ];
}
