<?php

namespace App\Domain\Cms;

use App\Models\Page;
use App\Models\PageDocument;
use stdClass;

final class PageRuntimePayloadFactory
{
    public function __construct(
        private readonly RuntimeAdapterRegistry $adapters,
        private readonly PageDocumentContract $contract,
        private readonly CmsFormSubmissionContracts $formContracts,
        private readonly CanonicalPublishedPagePolicy $publishedPages,
    ) {}

    /** @return array<string, mixed> */
    public function forDraft(Page $page): array
    {
        $document = $page->draftDocument;

        abort_if($document === null, 404);

        return $this->build($page, $document, 'preview', PageRuntimeAudience::PREVIEW);
    }

    /** @return array<string, mixed> */
    public function forCanary(Page $page): array
    {
        $document = $this->publishedPages->publishedDocument($page);

        return $this->build($page, $document, 'public', PageRuntimeAudience::CANARY);
    }

    /** @return array<string, mixed> */
    public function forOfficialPublishedSlug(string $slug): array
    {
        $page = $this->publishedPages->resolveBySlug($slug);
        $document = $page->getRelation('publishedDocument');

        return $this->build($page, $document, 'public', PageRuntimeAudience::PUBLIC);
    }

    /** @return array<string, mixed> */
    private function build(Page $page, PageDocument $pageDocument, string $mode, string $audience): array
    {
        $document = $this->contract->validate($pageDocument->document);
        $facts = $this->adapters->factsFor($document);

        return [
            'page' => [
                'id' => (string) $page->getKey(),
                'status' => $page->status,
                'stage' => $pageDocument->stage,
                'schemaVersion' => $pageDocument->schema_version,
                'lockVersion' => $pageDocument->lock_version,
                'contentHash' => $pageDocument->content_hash,
                'document' => $document,
            ],
            'catalog' => $facts['catalog'],
            'forms' => [
                ...$this->formContracts->forDocument($page, $pageDocument, $document, $audience),
            ],
            'links' => new stdClass,
            'runtime' => [
                'sections' => $facts['sections'],
                'errors' => $this->contract->runtimeErrors($document, $facts),
            ],
            'context' => [
                'mode' => $mode,
                'currency' => (string) app(\App\Support\CurrencySettings::class)->default()['code'],
            ],
        ];
    }
}
