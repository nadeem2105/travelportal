<?php

use App\Models\Menu;
use Illuminate\Support\Collection;

if (! function_exists('menu_tree')) {
    /**
     * Admin-managed menu for a location (header, footer_quick, footer_support...).
     * Falls back to sensible defaults until menus are configured.
     */
    function menu_tree(string $location): Collection
    {
        return cache()->remember("menu_{$location}", 600, function () use ($location) {
            $menu = Menu::with(['items' => fn ($q) => $q
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->with(['children' => fn ($c) => $c->where('status', 'active')]),
            ])
                ->where('location', $location)
                ->first();

            return collect($menu?->items ?? []);
        });
    }
}
