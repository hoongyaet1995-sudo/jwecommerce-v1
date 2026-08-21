<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim((string) ($validated['q'] ?? ''));

        return view('admin.search', [
            'query' => $query,
            'products' => Product::query()->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('handle', 'like', "%{$query}%"))->limit(5)->get(),
            'orders' => Order::query()->where(fn ($builder) => $builder->where('number', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))->latest()->limit(5)->get(),
            'customers' => Customer::query()->where(fn ($builder) => $builder->where('email', 'like', "%{$query}%")->orWhere('first_name', 'like', "%{$query}%")->orWhere('last_name', 'like', "%{$query}%"))->limit(5)->get(),
            'pages' => Page::query()->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('slug', 'like', "%{$query}%"))->limit(5)->get(),
        ]);
    }
}
