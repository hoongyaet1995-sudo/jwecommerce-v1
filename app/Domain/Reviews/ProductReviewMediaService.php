<?php

namespace App\Domain\Reviews;

use App\Models\ProductReview;
use App\Models\ProductReviewMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductReviewMediaService
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_PIXELS = 20_000_000;
    private const MAX_MEDIA = 5;

    /** @var array<string, array{extension: string, client_extensions: list<string>}> */
    private const IMAGE_TYPES = [
        'image/jpeg' => ['extension' => 'jpg', 'client_extensions' => ['jpg', 'jpeg']],
        'image/png' => ['extension' => 'png', 'client_extensions' => ['png']],
        'image/webp' => ['extension' => 'webp', 'client_extensions' => ['webp']],
    ];

    public function storePending(ProductReview $review, UploadedFile $file, int $position): ProductReviewMedia
    {
        if ($review->status !== 'pending') {
            $this->invalid('Images can only be attached to a pending review.');
        }
        if ($position < 0 || $position >= self::MAX_MEDIA || $review->media()->count() >= self::MAX_MEDIA) {
            $this->invalid('A review may include at most five images.');
        }

        $metadata = $this->inspect($file);
        $path = 'reviews/'.$review->id.'/originals/'.Str::uuid().'.'.$metadata['extension'];
        $stored = Storage::disk('review-private')->putFileAs(dirname($path), $file, basename($path));

        if ($stored !== $path) {
            throw new RuntimeException('The review image could not be stored privately.');
        }

        try {
            return $review->media()->create([
                'original_path' => $path,
                'original_filename' => $this->safeOriginalName($file),
                'mime_type' => $metadata['mime'],
                'byte_size' => $metadata['size'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'checksum_sha256' => $metadata['checksum'],
                'position' => $position,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('review-private')->delete($path);
            throw $exception;
        }
    }

    /** @return array{mime: string, extension: string, size: int, width: int, height: int, checksum: string} */
    private function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $size = $file->getSize();
        $mime = $file->getMimeType();
        if (! $file->isValid() || ! is_string($path) || $path === '' || ! is_int($size) || ! is_string($mime)) {
            $this->invalid('A valid review image is required.');
        }
        $type = self::IMAGE_TYPES[$mime] ?? null;
        if ($type === null || ! in_array(strtolower($file->getClientOriginalExtension()), $type['client_extensions'], true)) {
            $this->invalid('Review images must be JPEG, PNG, or WebP files with a matching extension.');
        }
        if ($size < 1 || $size > self::MAX_BYTES) {
            $this->invalid('Review images may not exceed 5 MiB.');
        }
        $dimensions = @getimagesize($path);
        if (! is_array($dimensions) || ($dimensions['mime'] ?? null) !== $mime) {
            $this->invalid('The review image content does not match its detected type.');
        }
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            $this->invalid('Review images may not exceed 20 megapixels.');
        }
        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum)) {
            throw new RuntimeException('Unable to checksum the review image.');
        }

        return ['mime' => $mime, 'extension' => $type['extension'], 'size' => $size, 'width' => $width, 'height' => $height, 'checksum' => $checksum];
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';

        return Str::limit($name !== '' ? $name : 'review-image', 255, '');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['images' => [$message]]);
    }
}
