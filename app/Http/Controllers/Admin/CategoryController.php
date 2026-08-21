<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);

        return view('admin.categories', [
            'categories' => Category::query()->withCount('products')->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($categories) => $categories->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }
    public function store(Request $request): RedirectResponse { Category::query()->create($request->validate(['slug' => ['required', 'alpha_dash', 'unique:categories,slug'], 'name' => ['required', 'string', 'max:255']])); return redirect()->route('admin.categories.index'); }
    public function update(Request $request, Category $category): RedirectResponse { $category->update($request->validate(['slug' => ['required', 'alpha_dash', 'unique:categories,slug,'.$category->id], 'name' => ['required', 'string', 'max:255']])); return redirect()->route('admin.categories.index'); }
    public function destroy(Category $category): RedirectResponse { $category->delete(); return redirect()->route('admin.categories.index'); }
}
