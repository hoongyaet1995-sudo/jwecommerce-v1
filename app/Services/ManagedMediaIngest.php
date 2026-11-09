<?php

namespace App\Services;

use App\Models\CmsMediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ManagedMediaIngest
{
    private const IMAGE_MAX_KIB = 10 * 1024;

    private const VIDEO_MAX_KIB = 30 * 1024;

    private const IMAGE_MAX_WIDTH = 8000;

    private const IMAGE_MAX_HEIGHT = 8000;

    private const IMAGE_MAX_PIXELS = 40_000_000;

    /** @var array<string, array{extension: string, client_extensions: list<string>, kind: 'image'|'video'}> */
    private const TYPES = [
        'image/jpeg' => ['extension' => 'jpg', 'client_extensions' => ['jpg', 'jpeg'], 'kind' => 'image'],
        'image/png' => ['extension' => 'png', 'client_extensions' => ['png'], 'kind' => 'image'],
        'image/webp' => ['extension' => 'webp', 'client_extensions' => ['webp'], 'kind' => 'image'],
        'image/gif' => ['extension' => 'gif', 'client_extensions' => ['gif'], 'kind' => 'image'],
        'video/mp4' => ['extension' => 'mp4', 'client_extensions' => ['mp4'], 'kind' => 'video'],
        'video/webm' => ['extension' => 'webm', 'client_extensions' => ['webm'], 'kind' => 'video'],
    ];

    public function ingest(UploadedFile $file, ?int $actorId): CmsMediaAsset
    {
        $metadata=$this->inspect($file);
        $id = (string) Str::uuid();
        $path = 'cms/media/'.$id.'.'.$metadata['extension'];
        $url = '/storage/'.$path;
        $stored = Storage::disk('public')->putFileAs('cms/media', $file, basename($path), 'public');

        if ($stored !== $path) throw new RuntimeException('The media asset could not be stored.');

        try {
            $asset = CmsMediaAsset::query()->create([
                'id' => $id,
                'disk' => 'public',
                'path' => $path,
                'url' => $url,
                'original_name' => $this->safeOriginalName($file),
                'mime_type' => $metadata['mime'],
                'size' => $metadata['size'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'checksum' => $metadata['checksum'],
                'uploader_id' => $actorId,
                'orphaned_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return $asset;
    }

    /** Read-only inspection; does not store files or create media records. */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $size = $file->getSize();
        $mime = $file->getMimeType();

        // XAMPP/libmagic reports valid ISO BMFF MP4 containers as either
        // application/mp4 or video/mp4. Normalize the server-detected alias.
        if ($mime === 'application/mp4') {
            $mime = 'video/mp4';
        }

        if (! is_string($path) || $path === '' || ! is_int($size) || ! is_string($mime)) {
            $this->invalid('The uploaded file could not be inspected.');
        }

        $type = self::TYPES[$mime] ?? null;

        if ($type === null) {
            $this->invalid('The selected file type is not allowed.');
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());

        if (! in_array($clientExtension, $type['client_extensions'], true)) {
            $this->invalid('The filename extension does not match the detected file type.');
        }

        $maxBytes = ($type['kind'] === 'image' ? self::IMAGE_MAX_KIB : self::VIDEO_MAX_KIB) * 1024;

        if ($size > $maxBytes) {
            $this->invalid($type['kind'] === 'image'
                ? 'Images may not exceed 10 MiB.'
                : 'Videos may not exceed 30 MiB.');
        }

        $width = null;
        $height = null;

        if ($type['kind'] === 'image') {
            $dimensions = @getimagesize($path);

            if (! is_array($dimensions)
                || ! isset($dimensions[0], $dimensions[1], $dimensions['mime'])
                || $dimensions['mime'] !== $mime) {
                $this->invalid('The image content does not match its detected type.');
            }

            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];

            if ($width < 1 || $height < 1
                || $width > self::IMAGE_MAX_WIDTH
                || $height > self::IMAGE_MAX_HEIGHT
                || $width * $height > self::IMAGE_MAX_PIXELS) {
                $this->invalid('Images may not exceed 8000x8000 pixels or 40 megapixels.');
            }

            if (! $this->hasSafeRasterBoundary($path, $mime, $size)) {
                $this->invalid('The image contains trailing or malformed data.');
            }
        } elseif (! $this->hasVideoStructure($path, $mime, $size)) {
            $this->invalid('The video content does not match its detected type.');
        }

        $checksum = hash_file('sha256', $path);

        if (! is_string($checksum)) {
            throw new RuntimeException('Unable to checksum the managed media upload.');
        }

        return [
            'mime' => $mime,
            'extension' => $type['extension'],
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'checksum' => $checksum,
        ];
    }

    private function hasSafeRasterBoundary(string $path, string $mime, int $size): bool
    {
        $head = $this->readBytes($path, 12);
        $tailLength = $mime === 'image/png' ? 12 : 2;
        $tail = $this->readBytes($path, $tailLength, max(0, $size - $tailLength));

        return match ($mime) {
            'image/jpeg' => str_starts_with($head, "\xff\xd8\xff") && $tail === "\xff\xd9",
            'image/png' => $head !== null
                && str_starts_with($head, "\x89PNG\r\n\x1a\n")
                && $tail === "\x00\x00\x00\x00IEND\xaeB\x60\x82",
            'image/gif' => $head !== null
                && (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'))
                && substr((string) $tail, -1) === ';',
            'image/webp' => $head !== null
                && strlen($head) === 12
                && substr($head, 0, 4) === 'RIFF'
                && substr($head, 8, 4) === 'WEBP'
                && unpack('Vsize', substr($head, 4, 4))['size'] === $size - 8,
            default => false,
        };
    }

    private function hasVideoStructure(string $path, string $mime, int $size): bool
    {
        if ($size < 1 || $size > self::VIDEO_MAX_KIB * 1024) {
            return false;
        }

        $bytes = @file_get_contents($path);

        if (! is_string($bytes) || strlen($bytes) !== $size) {
            return false;
        }

        return match ($mime) {
            'video/mp4' => $this->hasMp4Structure($bytes),
            'video/webm' => $this->hasWebmStructure($bytes),
            default => false,
        };
    }

    private function hasMp4Structure(string $bytes): bool
    {
        $boxes = $this->mp4Boxes($bytes, 0, strlen($bytes));

        if ($boxes === null || ($boxes[0]['type'] ?? null) !== 'ftyp') {
            return false;
        }

        $ftyp = $boxes[0];
        $moov = collect($boxes)->firstWhere('type', 'moov');
        $hasMdat = collect($boxes)->contains(static fn (array $box): bool => $box['type'] === 'mdat');

        if ($ftyp['payload_end'] - $ftyp['payload_start'] < 8 || ! is_array($moov) || ! $hasMdat) {
            return false;
        }

        $moovBoxes = $this->mp4Boxes($bytes, $moov['payload_start'], $moov['payload_end']);

        return is_array($moovBoxes)
            && collect($moovBoxes)->contains(static fn (array $box): bool => $box['type'] === 'trak');
    }

    /** @return list<array{type: string, payload_start: int, payload_end: int}>|null */
    private function mp4Boxes(string $bytes, int $start, int $end): ?array
    {
        $boxes = [];
        $offset = $start;

        while ($offset < $end) {
            if ($end - $offset < 8) {
                return null;
            }

            $size = unpack('Nsize', substr($bytes, $offset, 4))['size'];
            $type = substr($bytes, $offset + 4, 4);
            $headerSize = 8;

            if ($size === 1) {
                if ($end - $offset < 16) {
                    return null;
                }

                $wide = unpack('Nhigh/Nlow', substr($bytes, $offset + 8, 8));

                if ($wide['high'] !== 0) {
                    return null;
                }

                $size = $wide['low'];
                $headerSize = 16;
            }

            if ($size === 0 || $size < $headerSize || $size > $end - $offset) {
                return null;
            }

            $boxEnd = $offset + $size;
            $boxes[] = [
                'type' => $type,
                'payload_start' => $offset + $headerSize,
                'payload_end' => $boxEnd,
            ];
            $offset = $boxEnd;
        }

        return $offset === $end ? $boxes : null;
    }

    private function hasWebmStructure(string $bytes): bool
    {
        $offset = 0;
        $end = strlen($bytes);
        $header = $this->readEbmlElement($bytes, $offset, $end);

        if ($header === null || $header['id'] !== '1a45dfa3'
            || ! $this->ebmlPayloadContains($bytes, $header, '4282', 'webm')) {
            return false;
        }

        $segment = $this->readEbmlElement($bytes, $offset, $end);

        if ($segment === null || $segment['id'] !== '18538067' || $offset !== $end) {
            return false;
        }

        $segmentOffset = $segment['payload_start'];
        $hasTracks = false;
        $hasCluster = false;

        while ($segmentOffset < $segment['payload_end']) {
            $element = $this->readEbmlElement($bytes, $segmentOffset, $segment['payload_end']);

            if ($element === null) {
                return false;
            }

            if ($element['id'] === '1654ae6b') {
                $hasTracks = $this->ebmlPayloadContains($bytes, $element, 'ae');
            } elseif ($element['id'] === '1f43b675') {
                $hasCluster = $this->ebmlPayloadContains($bytes, $element, 'a3')
                    || $this->ebmlPayloadContains($bytes, $element, 'a1');
            }
        }

        return $segmentOffset === $segment['payload_end'] && $hasTracks && $hasCluster;
    }

    /** @param array{id: string, payload_start: int, payload_end: int} $parent */
    private function ebmlPayloadContains(string $bytes, array $parent, string $wantedId, ?string $payload = null): bool
    {
        $offset = $parent['payload_start'];
        $found = false;

        while ($offset < $parent['payload_end']) {
            $element = $this->readEbmlElement($bytes, $offset, $parent['payload_end']);

            if ($element === null) {
                return false;
            }

            if ($element['id'] === $wantedId
                && ($payload === null
                    || substr($bytes, $element['payload_start'], $element['payload_end'] - $element['payload_start']) === $payload)) {
                $found = true;
            }
        }

        return $offset === $parent['payload_end'] && $found;
    }

    /** @return array{id: string, payload_start: int, payload_end: int}|null */
    private function readEbmlElement(string $bytes, int &$offset, int $end): ?array
    {
        $id = $this->readEbmlVint($bytes, $offset, $end, true);
        $size = $this->readEbmlVint($bytes, $offset, $end, false);

        if (! is_string($id) || ! is_int($size) || $size > $end - $offset) {
            return null;
        }

        $payloadStart = $offset;
        $offset += $size;

        return ['id' => $id, 'payload_start' => $payloadStart, 'payload_end' => $offset];
    }

    private function readEbmlVint(string $bytes, int &$offset, int $end, bool $isId): int|string|null
    {
        if ($offset >= $end) {
            return null;
        }

        $first = ord($bytes[$offset]);
        $mask = 0x80;
        $length = 1;

        while ($length <= 8 && ($first & $mask) === 0) {
            $mask >>= 1;
            $length++;
        }

        if ($mask === 0 || $offset + $length > $end || ($isId && $length > 4)) {
            return null;
        }

        $raw = substr($bytes, $offset, $length);
        $offset += $length;

        if ($isId) {
            return bin2hex($raw);
        }

        $value = $first & ($mask - 1);
        $unknown = $value === $mask - 1;

        for ($index = 1; $index < $length; $index++) {
            $byte = ord($raw[$index]);
            $value = ($value << 8) | $byte;
            $unknown = $unknown && $byte === 0xFF;

            if ($value > $end) {
                return null;
            }
        }

        return $unknown ? null : $value;
    }

    private function readBytes(string $path, int $length, int $offset = 0): ?string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                return null;
            }

            $bytes = fread($handle, $length);

            return is_string($bytes) ? $bytes : null;
        } finally {
            fclose($handle);
        }
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';

        return Str::limit($name !== '' ? $name : 'upload', 255, '');
    }

    /** @throws ValidationException */
    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
