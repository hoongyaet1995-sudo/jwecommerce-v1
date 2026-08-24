<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,draft'],
            'sort' => ['nullable', 'in:title,base_price,inventory'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $direction = $filters['direction'] ?? 'asc';
        $products = Product::query()->with(['category', 'images', 'variants'])->withCount('variants')->withSum('variants', 'stock')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($catalog) => $catalog->where('title', 'like', "%{$search}%")->orWhere('handle', 'like', "%{$search}%")->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', "%{$search}%"))))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        match ($filters['sort'] ?? 'title') {
            'base_price' => $products->orderBy('base_price', $direction),
            'inventory' => $products->orderBy('variants_sum_stock', $direction),
            default => $products->orderBy('title', $direction),
        };

        $products = $products->get();

        return view('admin.products', ['products' => $products, 'categories' => Category::query()->orderBy('name')->get(), 'filters' => $filters]);
    }
    public function store(Request $request): RedirectResponse { $data = $request->validate(['category_id' => ['nullable', 'exists:categories,id'], 'handle' => ['required', 'alpha_dash', 'unique:products,handle'], 'title' => ['required', 'string', 'max:255'], 'base_price' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:draft,active']]); Product::query()->create($data); return redirect()->route('admin.products.index'); }
    public function update(Request $request, Product $product): RedirectResponse { $data = $request->validate(['category_id' => ['nullable', 'exists:categories,id'], 'handle' => ['required', 'alpha_dash', 'unique:products,handle,'.$product->id], 'title' => ['required', 'string', 'max:255'], 'base_price' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:draft,active']]); $product->update($data); return redirect()->route('admin.products.index'); }
    public function destroy(Product $product): RedirectResponse { $product->delete(); return redirect()->route('admin.products.index'); }
    public function storeVariant(Request $request, Product $product): RedirectResponse { $data = $request->validate(['sku' => ['required', 'string', 'unique:product_variants,sku'], 'title' => ['required', 'string'], 'price' => ['required', 'integer', 'min:0'], 'stock' => ['required', 'integer', 'min:0']]); $product->variants()->create($data); return redirect()->route('admin.products.index'); }
    public function storeImage(Request $request, Product $product): RedirectResponse { $data = $request->validate(['path' => $this->localImageRules(), 'alt' => ['nullable', 'string', 'max:255'], 'position' => ['required', 'integer', 'min:0']]); $product->images()->create($data); return redirect()->route('admin.products.index'); }
    public function updateImage(Request $request, ProductImage $productImage): RedirectResponse { $data = $request->validate(['path' => $this->localImageRules(), 'alt' => ['nullable', 'string', 'max:255'], 'position' => ['required', 'integer', 'min:0']]); $productImage->update($data); return redirect()->route('admin.products.index'); }
    public function destroyImage(ProductImage $productImage): RedirectResponse { $productImage->delete(); return redirect()->route('admin.products.index'); }

    private function localImageRules(): array
    {
        return [
            'required',
            'string',
            'max:255',
            'regex:#^/images/(?:catalog|source)/[A-Za-z0-9][A-Za-z0-9._-]*\\.(?:jpg|jpeg|png|webp)$#i',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_file(public_path($value))) {
                    $fail('The selected image must exist in the managed local asset catalog.');
                }
            },
        ];
    }
}
