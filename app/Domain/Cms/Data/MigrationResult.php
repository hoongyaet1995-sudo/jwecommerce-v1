<?php

namespace App\Domain\Cms\Data;

final readonly class MigrationResult
{
    /**
     * @param  list<string>  $sourceSectionIds
     * @param  list<string>  $sourceSectionTypes
     * @param list<array{
     *     id: string,
     *     content_keys: list<string>,
     *     output_keys: list<string>,
     *     item_key_sets: list<list<string>>
     * }> $sourceSectionKeySets
     * @param  list<string>  $keyLossPaths
     * @param  list<string>  $normalizations
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $pageId,
        public string $slug,
        public int $sourceSectionCount,
        public array $sourceSectionIds,
        public array $sourceSectionTypes,
        public array $sourceSectionKeySets,
        public string $sourceHash,
        public string $documentHash,
        public bool $publishedExpected,
        public bool $draftExists,
        public bool $publishedExists,
        public bool $statusMatches,
        public bool $canonicalVerified,
        public bool $verified,
        public array $keyLossPaths = [],
        public int $keyLossCount = 0,
        public bool $migrated = false,
        public bool $skipped = false,
        public array $normalizations = [],
        public array $errors = [],
    ) {}

    public function withOutcome(bool $migrated, bool $skipped): self
    {
        return new self(
            pageId: $this->pageId,
            slug: $this->slug,
            sourceSectionCount: $this->sourceSectionCount,
            sourceSectionIds: $this->sourceSectionIds,
            sourceSectionTypes: $this->sourceSectionTypes,
            sourceSectionKeySets: $this->sourceSectionKeySets,
            sourceHash: $this->sourceHash,
            documentHash: $this->documentHash,
            publishedExpected: $this->publishedExpected,
            draftExists: $this->draftExists,
            publishedExists: $this->publishedExists,
            statusMatches: $this->statusMatches,
            canonicalVerified: $this->canonicalVerified,
            verified: $this->verified,
            keyLossPaths: $this->keyLossPaths,
            keyLossCount: $this->keyLossCount,
            migrated: $migrated,
            skipped: $skipped,
            normalizations: $this->normalizations,
            errors: $this->errors,
        );
    }
}
