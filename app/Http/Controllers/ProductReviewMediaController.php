<?php

namespace App\Http\Controllers;

use App\Models\ProductReviewMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductReviewMediaController extends Controller
{
    public function showForCustomer(Request $request, ProductReviewMedia $media): StreamedResponse
    {
        $review = $media->review()->with('customer')->firstOrFail();
        $actor = $request->user('customer');
        abort_unless($actor?->can('viewPrivateMedia', $review), 403);
        abort_unless(Storage::disk('review-private')->exists($media->original_path), 404);

        return Storage::disk('review-private')->response($media->original_path, null, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="review-image"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function showForAdmin(Request $request, ProductReviewMedia $media): StreamedResponse
    {
        $review = $media->review()->with('customer')->firstOrFail();
        abort_unless($request->user()?->can('viewPrivateMedia', $review), 403);
        abort_unless(Storage::disk('review-private')->exists($media->original_path), 404);

        return Storage::disk('review-private')->response($media->original_path, null, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="review-image"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
