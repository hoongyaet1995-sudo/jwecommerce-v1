<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\OriginalAccountPayloadFactory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $request->user('customer')->customer()->with(['orders.items', 'orders.addresses', 'addresses', 'wishlistItems.product.images', 'wishlistItems.product.variants'])->firstOrFail();

        return view('storefront.account', compact('customer'));
    }

    public function originalPreview(Request $request, OriginalAccountPayloadFactory $factory): View
    {
        $customer = $request->user('customer')->customer;
        abort_unless($customer !== null, 403);

        return view('storefront.account-original-preview', [
            'customer' => $customer,
            'accountPayload' => $factory->for($customer),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);
        $customer = $request->user('customer')->customer;
        [$firstName, $lastName] = array_pad(explode(' ', trim($data['name']), 2), 2, null);

        DB::transaction(function () use ($request, $customer, $data, $firstName, $lastName): void {
            $request->user('customer')->update(['name' => $data['name']]);
            $customer->update(['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $data['phone'] ?? null]);
        });

        return redirect()->route('customer.account')->with('status', 'Profile updated.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'line_1' => ['required', 'string', 'max:255'],
            'line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $customer = $request->user('customer')->customer;

        DB::transaction(function () use ($customer, $data): void {
            if (($data['is_default'] ?? false) || ! $customer->addresses()->exists()) {
                $customer->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }
            $customer->addresses()->create($data);
        });

        return redirect()->route('customer.account')->with('status', 'Address saved.');
    }

    public function storeWishlist(Request $request, Product $product): RedirectResponse
    {
        $request->user('customer')->customer->wishlistItems()->firstOrCreate(['product_id' => $product->id]);

        return back()->with('status', 'Saved to wishlist.');
    }

    public function destroyWishlist(Request $request, Product $product): RedirectResponse
    {
        $request->user('customer')->customer->wishlistItems()->where('product_id', $product->id)->delete();

        return back()->with('status', 'Removed from wishlist.');
    }
}
