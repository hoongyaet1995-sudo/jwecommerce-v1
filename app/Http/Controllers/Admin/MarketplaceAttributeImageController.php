<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Marketplace\MarketplaceAttributeImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class MarketplaceAttributeImageController extends Controller
{
    public function __invoke(Request $request, Product $product, MarketplaceAttributeImageUpload $upload): JsonResponse
    {
        if (array_diff(array_keys($request->all()), ['file', '_token'])) {
            throw ValidationException::withMessages(['file' => 'Only the selected image file may be uploaded.']);
        }
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ]);
        $image = $upload->store($product, $validated['file'], (int) $request->user()->getKey());

        return response()->json([
            'imageId' => (int) $image->getKey(),
            'url' => $image->path,
            'alt' => $image->alt ?? '',
        ], 201)->header('Cache-Control', 'no-store, private');
    }
}
