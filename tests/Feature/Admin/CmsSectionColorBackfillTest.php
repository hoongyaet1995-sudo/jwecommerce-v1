<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageDocumentHasher;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CmsSectionColorBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_missing_color_objects_without_mutating_canonical_documents(): void
    {
        $page = $this->pageWithDocuments();
        $before = $page->draftDocument->only(['document', 'content_hash', 'lock_version']);

        $this->artisan('cms:backfill-section-colors')
            ->expectsOutputToContain('mode=dry-run')
            ->expectsOutputToContain('documents_to_update=2')
            ->assertExitCode(0);

        $after = $page->fresh()->draftDocument;

        $this->assertSame($before['document'], $after->document);
        $this->assertSame($before['content_hash'], $after->content_hash);
        $this->assertSame($before['lock_version'], $after->lock_version);
    }

    public function test_apply_fills_missing_color_keys_in_draft_and_published_documents_without_overwriting_existing_values(): void
    {
        $page = $this->pageWithDocuments();

        $this->artisan('cms:backfill-section-colors', ['--apply' => true])
            ->expectsOutputToContain('mode=apply')
            ->expectsOutputToContain('documents_updated=2')
            ->assertExitCode(0);

        $draft = $page->fresh()->draftDocument;
        $published = $page->fresh()->publishedDocument;

        $this->assertSame([
            'eyebrowColor' => '#818cf8',
            'titleColor' => '#ffffff',
            'contentColor' => '#d4d4d8',
            'primaryBtnBg' => '#4f46e5',
            'primaryBtnText' => '#ffffff',
            'secondaryBtnBg' => 'rgba(255, 255, 255, 0.1)',
            'secondaryBtnText' => '#ffffff',
            'customBgColor' => '',
        ], $draft->document['sections'][0]['customColors']);
        $this->assertSame('#123456', $published->document['sections'][0]['customColors']['titleColor']);
        $this->assertSame('#818cf8', $published->document['sections'][0]['customColors']['eyebrowColor']);
        $this->assertSame('', $published->document['sections'][0]['customColors']['customBgColor']);
        $this->assertSame(PageDocumentHasher::hash($draft->document), $draft->content_hash);
        $this->assertSame(PageDocumentHasher::hash($published->document), $published->content_hash);
        $this->assertSame(2, $draft->lock_version);
        $this->assertSame(2, $published->lock_version);
    }

    public function test_apply_is_idempotent_after_every_color_key_has_been_backfilled(): void
    {
        $page = $this->pageWithDocuments();

        $this->artisan('cms:backfill-section-colors', ['--apply' => true])->assertExitCode(0);
        $afterFirstApply = $page->fresh()->draftDocument->only(['document', 'content_hash', 'lock_version']);

        $this->artisan('cms:backfill-section-colors', ['--apply' => true])
            ->expectsOutputToContain('documents_to_update=0')
            ->expectsOutputToContain('documents_updated=0')
            ->assertExitCode(0);

        $afterSecondApply = $page->fresh()->draftDocument;

        $this->assertSame($afterFirstApply['document'], $afterSecondApply->document);
        $this->assertSame($afterFirstApply['content_hash'], $afterSecondApply->content_hash);
        $this->assertSame($afterFirstApply['lock_version'], $afterSecondApply->lock_version);
    }

    private function pageWithDocuments(): Page
    {
        $page = Page::query()->create([
            'status' => 'published',
            'is_published' => false,
        ]);

        $draft = $this->document('color-draft', []);
        $published = $this->document('color-published', ['titleColor' => '#123456']);

        $page->documents()->create([
            'stage' => PageDocument::STAGE_DRAFT,
            'slug' => $draft['slug'],
            'schema_version' => 1,
            'lock_version' => 1,
            'content_hash' => PageDocumentHasher::hash($draft),
            'document' => $draft,
        ]);
        $page->documents()->create([
            'stage' => PageDocument::STAGE_PUBLISHED,
            'slug' => $published['slug'],
            'schema_version' => 1,
            'lock_version' => 1,
            'content_hash' => PageDocumentHasher::hash($published),
            'document' => $published,
        ]);

        return $page->fresh();
    }

    /** @param array<string, string> $colors */
    private function document(string $slug, array $colors): array
    {
        $section = [
            'id' => 'section-hero',
            'type' => 'hero_banner',
            'title' => 'Color contract hero',
            'isVisible' => true,
        ];

        if ($colors !== []) {
            $section['customColors'] = $colors;
        }

        return [
            'title' => 'Color contract page',
            'slug' => $slug,
            'description' => '',
            'template' => 'standard',
            'heroImage' => null,
            'seoTitle' => '',
            'seoDescription' => '',
            'sections' => [$section],
        ];
    }
}
