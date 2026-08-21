<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\LegacyPageDocumentMigrator;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use stdClass;
use Tests\TestCase;

class LegacyCmsDocumentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_sections_are_composed_in_position_order_without_field_loss(): void
    {
        $page = Page::query()->create([
            'slug' => 'test-page-1',
            'title' => 'Test Page 1',
            'metadata' => [
                'description' => null,
                'template' => 'landing',
                'heroImage' => null,
                'seoTitle' => null,
                'seoDescription' => null,
            ],
            'is_published' => true,
        ]);
        $richText = $page->sections()->create([
            'type' => 'rich_text',
            'position' => 1,
            'content' => ['title' => 'Second', 'isVisible' => true],
        ]);
        $heroSlider = $page->sections()->create([
            'type' => 'hero_slider',
            'position' => 0,
            'content' => [
                'title' => 'First',
                'isVisible' => true,
                'items' => [[
                    'id' => 'slide-1',
                    'title' => 'Slide',
                    'subtitle' => '',
                    'price' => 0,
                    'isHighlighted' => false,
                    'features' => [],
                ]],
            ],
        ]);

        $legacyBefore = $this->legacyState($page->id);

        $result = app(LegacyPageDocumentMigrator::class)->migratePage($page);

        $freshPage = $page->fresh(['draftDocument', 'publishedDocument']);
        $document = $freshPage->draftDocument->document;

        $this->assertSame([
            'title',
            'slug',
            'description',
            'template',
            'heroImage',
            'seoTitle',
            'seoDescription',
            'sections',
        ], array_keys($document));
        $this->assertSame('', $document['description']);
        $this->assertSame('', $document['seoTitle']);
        $this->assertSame('', $document['seoDescription']);
        $this->assertSame(['hero_slider', 'rich_text'], array_column($document['sections'], 'type'));
        $this->assertSame([
            "legacy-page-{$page->id}-section-{$heroSlider->id}",
            "legacy-page-{$page->id}-section-{$richText->id}",
        ], array_column($document['sections'], 'id'));
        $this->assertSame(
            ['title', 'isVisible', 'items', 'id', 'type'],
            array_keys($document['sections'][0]),
        );
        $this->assertSame([
            'id' => 'slide-1',
            'title' => 'Slide',
            'subtitle' => '',
            'price' => 0,
            'isHighlighted' => false,
            'features' => [],
        ], $document['sections'][0]['items'][0]);
        $this->assertSame($document, $freshPage->publishedDocument->document);
        $this->assertSame('published', $freshPage->status);
        $this->assertTrue($result->verified);
        $this->assertTrue($result->migrated);
        $this->assertFalse($result->skipped);
        $this->assertSame($legacyBefore, $this->legacyState($page->id));
    }

    public function test_unpublished_legacy_page_creates_only_a_draft_and_maps_status(): void
    {
        $page = $this->createLegacyPage('draft-page', false);

        $result = app(LegacyPageDocumentMigrator::class)->migratePage($page);

        $freshPage = $page->fresh(['draftDocument', 'publishedDocument']);
        $this->assertNotNull($freshPage->draftDocument);
        $this->assertNull($freshPage->publishedDocument);
        $this->assertSame('draft', $freshPage->status);
        $this->assertFalse($result->publishedExpected);
        $this->assertTrue($result->verified);
    }

    public function test_verify_command_is_strictly_read_only_before_and_after_migration(): void
    {
        $page = $this->createLegacyPage('verify-page', true);
        $beforeDryRun = $this->databaseState();

        $this->artisan('cms:migrate-page-documents', ['--verify' => true])
            ->assertExitCode(0);

        $this->assertSame($beforeDryRun, $this->databaseState());

        app(LegacyPageDocumentMigrator::class)->migratePage($page);
        $beforePostMigrationVerify = $this->databaseState();

        $this->artisan('cms:migrate-page-documents', ['--verify' => true])
            ->assertExitCode(0);

        $this->assertSame($beforePostMigrationVerify, $this->databaseState());
    }

    public function test_normal_command_rerun_skips_verified_documents_without_overwriting_them(): void
    {
        $page = $this->createLegacyPage('idempotent-page', true);

        $this->artisan('cms:migrate-page-documents')->assertExitCode(0);
        $afterFirstRun = $this->databaseState();

        $this->artisan('cms:migrate-page-documents')->assertExitCode(0);

        $this->assertSame($afterFirstRun, $this->databaseState());

        $result = app(LegacyPageDocumentMigrator::class)->migratePage($page->fresh());
        $this->assertTrue($result->verified);
        $this->assertFalse($result->migrated);
        $this->assertTrue($result->skipped);
    }

    public function test_verify_accepts_a_canonical_empty_object_token_and_model_hash(): void
    {
        $page = $this->createLegacyPage('canonical-empty-object', false);
        $section = $page->sections()->firstOrFail();
        $section->update([
            'content' => array_merge($section->content, ['customColors' => []]),
        ]);
        app(LegacyPageDocumentMigrator::class)->migratePage($page);
        $draft = $page->fresh()->draftDocument;
        $canonical = $draft->document;
        $canonical['sections'][0]['customColors'] = new stdClass;
        $canonicalHash = PageDocumentHasher::hash($canonical);

        DB::table('page_documents')->where('id', $draft->id)->update([
            'document' => json_encode($canonical, JSON_THROW_ON_ERROR),
            'content_hash' => $canonicalHash,
        ]);

        $persisted = $draft->fresh();
        $result = app(LegacyPageDocumentMigrator::class)->verifyPage($page);

        $this->assertInstanceOf(stdClass::class, $persisted->document['sections'][0]['customColors']);
        $this->assertSame($persisted->content_hash, PageDocumentHasher::hash($persisted->document));
        $this->assertTrue($result->canonicalVerified, implode('; ', $result->errors));
    }

    public function test_verify_still_detects_a_true_canonical_document_mismatch(): void
    {
        $page = $this->createLegacyPage('true-canonical-mismatch', false);
        app(LegacyPageDocumentMigrator::class)->migratePage($page);
        $draft = $page->fresh()->draftDocument;
        $mismatched = $draft->document;
        $mismatched['title'] = 'Stored title no longer matches the legacy source';
        $draft->update([
            'document' => $mismatched,
            'content_hash' => PageDocumentHasher::hash($mismatched),
        ]);

        $result = app(LegacyPageDocumentMigrator::class)->verifyPage($page);

        $this->assertFalse($result->canonicalVerified);
        $this->assertTrue(collect($result->errors)->contains(
            static fn (string $error): bool => str_contains(
                $error,
                'content hash does not match the composed legacy document',
            ),
        ));
    }

    public function test_normal_rerun_refuses_to_overwrite_a_mismatched_canonical_document(): void
    {
        $page = $this->createLegacyPage('mismatched-page', false);
        app(LegacyPageDocumentMigrator::class)->migratePage($page);

        $draft = $page->fresh()->draftDocument;
        $draft->update([
            'content_hash' => str_repeat('f', 64),
            'lock_version' => 17,
        ]);
        $canonicalBefore = $this->databaseState()['page_documents'];

        try {
            app(LegacyPageDocumentMigrator::class)->migratePage($page->fresh());
            $this->fail('A normal rerun must refuse mismatched canonical documents.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Refusing to overwrite', $exception->getMessage());
        }

        $this->assertSame($canonicalBefore, $this->databaseState()['page_documents']);
    }

    public function test_contract_mismatch_is_reported_without_filtering_or_writing(): void
    {
        $page = Page::query()->create([
            'slug' => 'invalid-page',
            'title' => 'Invalid Page',
            'metadata' => ['inventedMetadata' => 'must not disappear'],
            'is_published' => false,
        ]);
        $page->sections()->create([
            'type' => 'rich_text',
            'position' => 0,
            'content' => [
                'title' => 'Invalid section',
                'isVisible' => true,
                'inventedField' => 'must not disappear',
            ],
        ]);

        try {
            app(LegacyPageDocumentMigrator::class)->migratePage($page);
            $this->fail('A legacy/contract mismatch must abort migration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('metadata.inventedMetadata', $exception->errors());
        }

        $this->assertDatabaseCount('page_documents', 0);
        $this->assertDatabaseHas('page_sections', [
            'page_id' => $page->id,
            'type' => 'rich_text',
        ]);
    }

    public function test_page_document_creation_and_status_mapping_are_atomic(): void
    {
        $blockingPage = Page::query()->create([
            'slug' => 'blocking-page',
            'title' => 'Blocking Page',
            'is_published' => false,
        ]);
        $blockingPage->documents()->create([
            'stage' => PageDocument::STAGE_PUBLISHED,
            'slug' => 'atomic-page',
            'schema_version' => 1,
            'lock_version' => 1,
            'content_hash' => str_repeat('a', 64),
            'document' => ['title' => 'Blocking', 'slug' => 'atomic-page', 'sections' => []],
        ]);
        $page = $this->createLegacyPage('atomic-page', true);
        $legacyBefore = $this->legacyState($page->id);

        try {
            app(LegacyPageDocumentMigrator::class)->migratePage($page);
            $this->fail('The published-document collision must abort the page transaction.');
        } catch (QueryException) {
            // The database constraint is the intentionally injected failure boundary.
        }

        $this->assertDatabaseCount('page_documents', 1);
        $this->assertDatabaseMissing('page_documents', [
            'page_id' => $page->id,
            'stage' => PageDocument::STAGE_DRAFT,
        ]);
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertSame($legacyBefore, $this->legacyState($page->id));
    }

    public function test_force_option_is_rejected_in_production_without_writes(): void
    {
        $page = $this->createLegacyPage('production-force-page', false);
        $before = $this->databaseState();
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->artisan('cms:migrate-page-documents', ['--force' => true])
            ->assertExitCode(1);

        $this->assertSame($before, $this->databaseState());
        $this->assertDatabaseMissing('page_documents', ['page_id' => $page->id]);
    }

    public function test_force_recovers_a_partial_canonical_state_in_non_production(): void
    {
        $page = $this->createLegacyPage('force-recovery-page', true);
        $legacyBefore = $this->legacyState($page->id);
        $staleDocument = [
            'title' => 'Stale canonical draft',
            'slug' => 'force-recovery-page',
            'description' => '',
            'template' => 'standard',
            'heroImage' => null,
            'seoTitle' => '',
            'seoDescription' => '',
            'sections' => [],
        ];
        $page->documents()->create([
            'stage' => PageDocument::STAGE_DRAFT,
            'slug' => 'force-recovery-page',
            'schema_version' => 1,
            'lock_version' => 9,
            'content_hash' => PageDocumentHasher::hash($staleDocument),
            'document' => $staleDocument,
        ]);

        $this->artisan('cms:migrate-page-documents', ['--force' => true])
            ->expectsOutputToContain('migrated_pages=1')
            ->expectsOutputToContain('failed_pages=0')
            ->assertExitCode(0);

        $freshPage = $page->fresh(['draftDocument', 'publishedDocument']);
        $this->assertSame('published', $freshPage->status);
        $this->assertNotNull($freshPage->draftDocument);
        $this->assertNotNull($freshPage->publishedDocument);
        $this->assertSame(1, $freshPage->draftDocument->lock_version);
        $this->assertSame(
            $freshPage->draftDocument->document,
            $freshPage->publishedDocument->document,
        );
        $this->assertSame($legacyBefore, $this->legacyState($page->id));
    }

    public function test_force_rejects_invalid_source_without_mutating_partial_canonical_state(): void
    {
        $page = $this->createLegacyPage('force-invalid-source', false);
        $page->update([
            'metadata' => array_merge($page->metadata, ['inventedMetadata' => 'must survive']),
        ]);
        $sentinelDocument = [
            'title' => 'Sentinel',
            'slug' => 'force-invalid-source',
            'description' => '',
            'template' => 'standard',
            'heroImage' => null,
            'seoTitle' => '',
            'seoDescription' => '',
            'sections' => [],
        ];
        $page->documents()->create([
            'stage' => PageDocument::STAGE_DRAFT,
            'slug' => 'force-invalid-source',
            'schema_version' => 1,
            'lock_version' => 7,
            'content_hash' => PageDocumentHasher::hash($sentinelDocument),
            'document' => $sentinelDocument,
        ]);
        $before = $this->databaseState();

        $this->artisan('cms:migrate-page-documents', ['--force' => true])
            ->expectsOutputToContain('metadata.inventedMetadata')
            ->assertExitCode(1);

        $this->assertSame($before, $this->databaseState());
    }

    public function test_verify_reports_nested_item_key_loss_with_a_real_count_and_path(): void
    {
        $page = $this->createLegacyItemPage('nested-key-loss', [
            [
                'id' => 'slide-one',
                'title' => 'One',
                'subtitle' => 'Must remain',
                'description' => 'First slide',
            ],
        ]);
        app(LegacyPageDocumentMigrator::class)->migratePage($page);
        $draft = $page->fresh()->draftDocument;
        $document = $draft->document;
        unset($document['sections'][0]['items'][0]['subtitle']);
        $draft->update([
            'document' => $document,
            'content_hash' => PageDocumentHasher::hash($document),
        ]);
        $beforeVerify = $this->databaseState();

        $result = app(LegacyPageDocumentMigrator::class)->verifyPage($page);

        $this->assertFalse($result->verified);
        $this->assertSame(1, $result->keyLossCount);
        $this->assertTrue(collect($result->errors)->contains(
            static fn (string $error): bool => str_contains(
                $error,
                'draft.sections.0.items.0.subtitle',
            ),
        ));

        $this->artisan('cms:migrate-page-documents', ['--verify' => true])
            ->expectsOutputToContain('draft.sections.0.items.0.subtitle')
            ->expectsOutputToContain('key_losses=1')
            ->assertExitCode(1);

        $this->assertSame($beforeVerify, $this->databaseState());
    }

    public function test_verify_reports_nested_item_id_order_mismatch(): void
    {
        $page = $this->createLegacyItemPage('nested-item-order', [
            ['id' => 'slide-one', 'title' => 'One'],
            ['id' => 'slide-two', 'title' => 'Two'],
        ]);
        app(LegacyPageDocumentMigrator::class)->migratePage($page);
        $draft = $page->fresh()->draftDocument;
        $document = $draft->document;
        $document['sections'][0]['items'] = array_reverse($document['sections'][0]['items']);
        $draft->update([
            'document' => $document,
            'content_hash' => PageDocumentHasher::hash($document),
        ]);

        $result = app(LegacyPageDocumentMigrator::class)->verifyPage($page);

        $this->assertFalse($result->verified);
        $this->assertSame(0, $result->keyLossCount);
        $this->assertTrue(collect($result->errors)->contains(
            static fn (string $error): bool => str_contains(
                $error,
                'draft.sections.0.items item ids or order',
            ),
        ));
    }

    public function test_multi_page_failure_reports_committed_and_failed_outcomes_truthfully(): void
    {
        $firstPage = $this->createLegacyPage('shared-canonical-slug', false);
        $secondPage = $this->createLegacyPage('shared-canonical-slug', false);
        $firstLegacyBefore = $this->legacyState($firstPage->id);
        $secondLegacyBefore = $this->legacyState($secondPage->id);

        $this->artisan('cms:migrate-page-documents')
            ->expectsOutputToContain('mode=migrate')
            ->expectsOutputToContain('pages=2')
            ->expectsOutputToContain('migrated_pages=1')
            ->expectsOutputToContain('failed_pages=1')
            ->expectsOutputToContain('partial_commit=true')
            ->assertExitCode(1);

        $this->assertDatabaseHas('page_documents', [
            'page_id' => $firstPage->id,
            'stage' => PageDocument::STAGE_DRAFT,
            'slug' => 'shared-canonical-slug',
        ]);
        $this->assertDatabaseMissing('page_documents', [
            'page_id' => $secondPage->id,
        ]);
        $this->assertSame($firstLegacyBefore, $this->legacyState($firstPage->id));
        $this->assertSame($secondLegacyBefore, $this->legacyState($secondPage->id));
    }

    private function createLegacyPage(string $slug, bool $published): Page
    {
        $page = Page::query()->create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'metadata' => [
                'description' => '',
                'template' => 'standard',
                'heroImage' => null,
                'seoTitle' => '',
                'seoDescription' => '',
            ],
            'is_published' => $published,
        ]);
        $page->sections()->create([
            'type' => 'rich_text',
            'position' => 0,
            'content' => ['title' => 'Legacy title', 'isVisible' => true],
        ]);

        return $page;
    }

    /** @param list<array<string, mixed>> $items */
    private function createLegacyItemPage(string $slug, array $items): Page
    {
        $page = Page::query()->create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'metadata' => [
                'description' => '',
                'template' => 'standard',
                'heroImage' => null,
                'seoTitle' => '',
                'seoDescription' => '',
            ],
            'is_published' => false,
        ]);
        $page->sections()->create([
            'type' => 'hero_slider',
            'position' => 0,
            'content' => [
                'title' => 'Legacy slider',
                'isVisible' => true,
                'items' => $items,
            ],
        ]);

        return $page;
    }

    /** @return array<string, mixed> */
    private function legacyState(int $pageId): array
    {
        return [
            'page' => (array) DB::table('pages')
                ->select(['id', 'slug', 'title', 'metadata', 'is_published', 'created_at', 'updated_at'])
                ->where('id', $pageId)
                ->first(),
            'sections' => DB::table('page_sections')
                ->where('page_id', $pageId)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function databaseState(): array
    {
        return [
            'pages' => DB::table('pages')->orderBy('id')->get()
                ->map(static fn (object $row): array => (array) $row)->all(),
            'page_sections' => DB::table('page_sections')->orderBy('id')->get()
                ->map(static fn (object $row): array => (array) $row)->all(),
            'page_documents' => DB::table('page_documents')->orderBy('id')->get()
                ->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }
}
