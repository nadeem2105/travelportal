<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with('items.children')->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'location' => 'required|in:header,footer_quick,footer_support,footer_legal',
        ]);

        $menu = Menu::create($validated + ['is_default' => false]);

        ActivityLogger::log('create', 'menus', "Created menu {$menu->name}");

        return back()->with('success', 'Menu created. Add items next.');
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'location' => 'required|in:header,footer_quick,footer_support,footer_legal',
        ]);

        $menu->update($validated);

        return back()->with('success', 'Menu updated.');
    }

    public function destroy(Menu $menu)
    {
        ActivityLogger::log('delete', 'menus', "Deleted menu {$menu->name}");
        $menu->delete();

        return back()->with('success', 'Menu deleted.');
    }

    public function storeItem(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:menu_items,id',
            'label' => 'required|string|max:80',
            'url' => 'required|string|max:255',
            'target' => 'required|in:_self,_blank',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $menu->items()->create($validated + ['status' => 'active']);

        return back()->with('success', 'Menu item added.');
    }

    public function updateItem(Request $request, MenuItem $item)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:80',
            'url' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $item->update($validated);

        return back()->with('success', 'Menu item updated.');
    }

    public function destroyItem(MenuItem $item)
    {
        $item->children()->delete();
        $item->delete();

        return back()->with('success', 'Menu item removed.');
    }
}
