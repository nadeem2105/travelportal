<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactGroup extends Model
{
    protected $table = 'contact_groups';

    protected $fillable = [
        'name', 'type', 'description', 'filters', 'created_by',
    ];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group_members', 'contact_group_id', 'contact_id')
            ->withTimestamps();
    }

    public function isDynamic(): bool
    {
        return $this->type === 'dynamic';
    }

    /** Build the query that yields this group's contacts (static pivot or dynamic filter). */
    public function contactsQuery(): Builder
    {
        if (! $this->isDynamic()) {
            return Contact::whereHas('contactGroups', fn ($q) => $q->where('contact_groups.id', $this->id));
        }

        $filters = $this->filters ?? [];

        return Contact::query()
            ->when($filters['lifecycle_stage'] ?? null, fn ($q, $v) => $q->where('lifecycle_stage', $v))
            ->when($filters['source_id'] ?? null, fn ($q, $v) => $q->where('source_id', $v))
            ->when(array_key_exists('whatsapp_opt_in', $filters) && $filters['whatsapp_opt_in'] !== null && $filters['whatsapp_opt_in'] !== '',
                fn ($q) => $q->where('whatsapp_opt_in', (bool) $filters['whatsapp_opt_in']))
            ->when($filters['tag_id'] ?? null, fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $v)));
    }

    /** Resolve the contacts (optionally only WhatsApp-reachable). */
    public function resolveContacts(bool $whatsappReachableOnly = false)
    {
        $query = $this->contactsQuery();

        if ($whatsappReachableOnly) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        return $query->get();
    }

    public function memberCount(): int
    {
        return $this->contactsQuery()->count();
    }
}
