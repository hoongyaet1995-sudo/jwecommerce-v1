<?php

namespace App\Domain\Cms;

use App\Models\Page;
use App\Models\PageDocument;
use App\Models\User;

final class CanonicalPublishedPagePolicy
{
    public function resolveBySlug(string $slug): Page
    {
        $document = PageDocument::query()
            ->with('page')
            ->where('stage', PageDocument::STAGE_PUBLISHED)
            ->where('slug', $slug)
            ->first();

        abort_if($document === null || $document->page === null, 404);
        abort_unless($this->isEligible($document->page, $document), 404);

        return $document->page->setRelation('publishedDocument', $document);
    }

    public function publishedDocument(Page $page): PageDocument
    {
        $document = PageDocument::query()
            ->where('page_id', $page->getKey())
            ->where('stage', PageDocument::STAGE_PUBLISHED)
            ->first();

        abort_unless($document !== null && $this->isEligible($page, $document), 404);

        return $document;
    }

    public function isEligible(Page $page, PageDocument $document): bool
    {
        return $page->status === 'published'
            && $document->stage === PageDocument::STAGE_PUBLISHED
            && (int) $document->page_id === (int) $page->getKey();
    }

    public function maySubmit(Page $page, PageDocument $document, string $audience, ?User $user): bool
    {
        if ($document->stage === PageDocument::STAGE_DRAFT) {
            return $audience === PageRuntimeAudience::PREVIEW && $user?->is_admin === true;
        }

        if (! $this->isEligible($page, $document)) {
            return false;
        }

        return match ($audience) {
            PageRuntimeAudience::CANARY => $user?->is_admin === true,
            PageRuntimeAudience::PUBLIC => true,
            default => false,
        };
    }
}
