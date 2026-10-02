<?php

namespace App\Services\Crm;

use App\Models\Admin;
use App\Models\CrmLead;
use App\Models\LeadSource;

/**
 * Resolves which staff member a lead should be assigned to. Never returns an
 * inactive staff member. Supports round-robin (workload-balanced) and
 * source-based routing configured in config/crm.php.
 */
class LeadAssignmentService
{
    public function resolve(array $context = []): ?int
    {
        $strategy = config('crm.assignment.strategy', 'round_robin');

        if ($strategy === 'source' && ! empty($context['source_id'])) {
            if ($id = $this->bySource((int) $context['source_id'])) {
                return $id;
            }
        }

        if ($strategy === 'none') {
            return null;
        }

        return $this->roundRobin();
    }

    /** Route by source rule (source slug => role slug) when configured. */
    protected function bySource(int $sourceId): ?int
    {
        $rules = (array) config('crm.assignment.source_rules', []);
        if (! $rules) {
            return null;
        }

        $source = LeadSource::find($sourceId);
        if (! $source || empty($rules[$source->slug])) {
            return null;
        }

        $roleSlug = $rules[$source->slug];

        $pool = $this->activeStaff()
            ->filter(fn (Admin $a) => method_exists($a, 'roles')
                ? $a->roles->contains('slug', $roleSlug)
                : false);

        return $this->leastLoaded($pool->pluck('id')->all());
    }

    /** Workload-balanced round robin across active staff. */
    protected function roundRobin(): ?int
    {
        return $this->leastLoaded($this->activeStaff()->pluck('id')->all());
    }

    protected function activeStaff()
    {
        return Admin::query()->where('status', 'active')->get();
    }

    /** Pick the staff id with the fewest currently-open leads. */
    protected function leastLoaded(array $staffIds): ?int
    {
        $staffIds = array_values(array_filter($staffIds));
        if (! $staffIds) {
            return null;
        }

        $counts = CrmLead::query()
            ->open()
            ->whereIn('assigned_to', $staffIds)
            ->selectRaw('assigned_to, COUNT(*) as c')
            ->groupBy('assigned_to')
            ->pluck('c', 'assigned_to')
            ->all();

        $best = null;
        $bestCount = PHP_INT_MAX;
        foreach ($staffIds as $id) {
            $c = (int) ($counts[$id] ?? 0);
            if ($c < $bestCount) {
                $bestCount = $c;
                $best = $id;
            }
        }

        return $best;
    }
}
