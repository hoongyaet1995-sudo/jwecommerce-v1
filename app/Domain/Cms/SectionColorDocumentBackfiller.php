<?php

namespace App\Domain\Cms;

use App\Models\PageDocument;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use stdClass;

final class SectionColorDocumentBackfiller
{
    /** @var array<string, mixed>|null */
    private static ?array $manifest = null;

    public function __construct(
        private readonly PageDocumentContract $contract,
        private readonly PageDocumentJsonCodec $documents,
    ) {}

    /**
     * @return array{changed: bool, sections_normalized: int, document: array<string, mixed>, content_hash: string}
     */
    public function inspect(PageDocument $document): array
    {
        $source = $this->documents->canonicalizeDocument($document->document);
        $this->contract->validate($source);

        $normalized = $source;
        $sectionsNormalized = 0;

        foreach ($normalized['sections'] as $index => $section) {
            if (! $this->supportsCustomColors($section['type'])) {
                continue;
            }

            $current = array_key_exists('customColors', $section)
                ? $this->colorArray($section['customColors'])
                : [];
            $next = array_replace($this->defaults(), $current);

            if (! array_key_exists('customColors', $section) || $current !== $next) {
                $normalized['sections'][$index]['customColors'] = $next;
                $sectionsNormalized++;
            }
        }

        $this->contract->validate($normalized);

        return [
            'changed' => $normalized !== $source,
            'sections_normalized' => $sectionsNormalized,
            'document' => $normalized,
            'content_hash' => PageDocumentHasher::hash($normalized),
        ];
    }

    /** @return array{changed: bool, sections_normalized: int, document: array<string, mixed>, content_hash: string} */
    public function apply(int $documentId): array
    {
        return DB::transaction(function () use ($documentId): array {
            $document = PageDocument::query()->lockForUpdate()->findOrFail($documentId);
            $result = $this->inspect($document);

            if (! $result['changed']) {
                return $result;
            }

            $document->forceFill([
                'document' => $result['document'],
                'content_hash' => $result['content_hash'],
                'lock_version' => $document->lock_version + 1,
            ])->save();

            return $result;
        });
    }

    /** @param array<string, mixed>|stdClass $colors */
    private function colorArray(array|stdClass $colors): array
    {
        return $colors instanceof stdClass ? get_object_vars($colors) : $colors;
    }

    private function supportsCustomColors(string $type): bool
    {
        return in_array(
            'customColors',
            $this->manifest()['section']['sectionTypes'][$type]['fields'] ?? [],
            true,
        );
    }

    /** @return array<string, string> */
    private function defaults(): array
    {
        $defaults = $this->manifest()['definitions']['sectionColorConfig']['defaults'] ?? null;

        if (! is_array($defaults) || $defaults === []) {
            throw new RuntimeException('CMS section color defaults are missing from the page contract.');
        }

        foreach ($defaults as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                throw new RuntimeException('CMS section color defaults must be string keys and values.');
            }
        }

        return $defaults;
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = resource_path('cms/page-contract.v1.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("CMS page contract is unreadable at [{$path}].");
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('CMS page contract is invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || ($manifest['version'] ?? null) !== PageDocumentContract::VERSION) {
            throw new RuntimeException('CMS page contract version does not match the document contract.');
        }

        return self::$manifest = $manifest;
    }
}
