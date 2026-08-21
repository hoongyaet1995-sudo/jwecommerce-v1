<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(): View { return view('admin.collections', ['collections' => Collection::query()->with(['products'])->withCount('products')->orderBy('title')->get(), 'products' => Product::query()->orderBy('title')->get()]); }
    public function store(Request $request): RedirectResponse { Collection::query()->create($request->validate(['slug' => ['required', 'alpha_dash', 'unique:collections,slug'], 'title' => ['required', 'string', 'max:255']])); return redirect()->route('admin.collections.index'); }
    public function update(Request $request, Collection $collection): RedirectResponse { $collection->update($request->validate(['slug' => ['required', 'alpha_dash', 'unique:collections,slug,'.$collection->id], 'title' => ['required', 'string', 'max:255'], 'subtitle' => ['nullable', 'string', 'max:255'], 'is_active' => ['required', 'boolean']])); return redirect()->route('admin.collections.index'); }
    public function destroy(Collection $collection): RedirectResponse { $collection->delete(); return redirect()->route('admin.collections.index'); }
    public function addProduct(Request $request, Collection $collection): RedirectResponse { $data = $request->validate(['product_id' => ['required','exists:products,id']]); $collection->products()->syncWithoutDetaching([$data['product_id']]); return redirect()->route('admin.collections.index'); }
}
