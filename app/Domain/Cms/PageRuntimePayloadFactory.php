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
    ) {}

    /** @return array<string, mixed> */
    public function forDraft(Page $page): array
    {
        $document = $page->draftDocument;

        abort_if($document === null, 404);

        return $this->build($page, $document, 'preview');
    }

    /** @return array<string, mixed> */
    public function forPublished(Page $page): array
    {
        $document = $page->publishedDocument;

        abort_unless($page->status === 'published' && $document !== null, 404);

        return $this->build($page, $document, 'public');
    }

    /** @return array<string, mixed> */
    private function build(Page $page, PageDocument $pageDocument, string $mode): array
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
                ...$this->formContracts->forDocument($page, $pageDocument, $document),
            ],
            'links' => new stdClass,
            'runtime' => [
                'sections' => $facts['sections'],
                'errors' => $this->contract->runtimeErrors($document, $facts),
            ],
            'context' => [
                'mode' => $mode,
                'currency' => (string) config('app.currency', 'USD'),
            ],
        ];
    }
}
