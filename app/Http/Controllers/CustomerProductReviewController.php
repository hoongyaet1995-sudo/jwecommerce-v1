<?php

namespace App\Http\Controllers;

use App\Domain\Reviews\ProductReviewSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerProductReviewController extends Controller
{
    public function preview(Request $request): View
    {
        $customer = $request->user('customer')->customer;
        abort_unless($customer !== null, 403);
        $item = $customer->orders()
            ->where('status', 'delivered')
            ->with(['items.productVariant.product', 'items.reviews'])
            ->latest()
            ->get()
            ->flatMap(fn ($order) => $order->items->map(fn ($item) => [$order, $item]))
            ->first(fn (array $pair) => ! $pair[1]->reviews->contains(fn ($review) => in_array($review->status, ['pending', 'published'], true)));

        return view('storefront.account-review-preview', [
            'customer' => $customer,
            'order' => $item[0] ?? null,
            'item' => $item[1] ?? null,
        ]);
    }

    public function store(Request $request, ProductReviewSubmissionService $reviews): JsonResponse
    {
        $data = $request->validate([
            'orderItemId' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['required', 'string', 'max:255'],
            'comment' => ['required', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['file'],
        ]);
        $customer = $request->user('customer')->customer;
        abort_unless($customer !== null, 403);
        $review = $reviews->submit($customer, (int) $data['orderItemId'], $data, $request->file('images', []));

        return response()->json(['data' => [
            'id' => $review->id,
            'status' => $review->status,
            'attemptNumber' => $review->attempt_number,
        ]], 201);
    }
}
