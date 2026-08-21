<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function menus(): View
    {
        return view('admin.menus', ['menus' => Menu::query()->with('items.children.children')->orderBy('name')->get()]);
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        Menu::query()->create($request->validate(['key' => ['required', 'alpha_dash', 'unique:menus,key'], 'name' => ['required', 'string', 'max:255']]));

        return redirect()->route('admin.menus.index');
    }

    public function storeMenuItem(Request $request, Menu $menu): RedirectResponse
    {
        $menu->items()->create($this->menuItemData($request));

        return redirect()->route('admin.menus.index');
    }

    public function updateMenuItem(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($this->menuItemData($request));

        return redirect()->route('admin.menus.index');
    }

    public function destroyMenuItem(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->delete();

        return redirect()->route('admin.menus.index');
    }

    private function menuItemData(Request $request): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'label' => ['required', 'string', 'max:255'],
            'label_cn' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'position' => ['required', 'integer', 'min:0'],
            'badge' => ['nullable', 'string', 'max:80'],
            'badge_type' => ['nullable', 'in:popular,sale,accent,hot,new,discount,rated,default'],
            'featured_title' => ['nullable', 'string', 'max:255'],
            'featured_description' => ['nullable', 'string', 'max:1000'],
            'featured_price' => ['nullable', 'string', 'max:80'],
            'featured_image_url' => ['nullable', 'string', 'max:2048'],
            'featured_cta_label' => ['nullable', 'string', 'max:255'],
            'featured_cta_url' => ['nullable', 'string', 'max:2048'],
        ]);
    }
}
