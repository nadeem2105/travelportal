<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }

    public function attachTag(Tag $tag): void
    {
        $this->tags()->syncWithoutDetaching([$tag->id]);
    }

    public function detachTag(Tag $tag): void
    {
        $this->tags()->detach($tag->id);
    }
}
