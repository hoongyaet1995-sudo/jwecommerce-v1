<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Domain\Cms\PageDocumentJsonCodec;
use App\Domain\Cms\PagePublicationService;
use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use JsonException;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class PagePublicationApiTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();config(['search.require_review'=>false]);} // Isolate legacy publication contract; new review proof covered in SearchFoundationTest.


    public function test_editing_published_page_does_not_change_public_snapshot(): void
    {
        $original = $this->document('published-edit', 'Published title');
        $page = $this->canonicalPage($original);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.hasUnpublishedChanges', false);

        $publishedBefore = $page->fresh()->publishedDocument;
        $publishedValues = $publishedBefore->only([
            'slug',
            'schema_version',
            'lock_version',
            'content_hash',
            'document',
        ]);
        $changed = $original;
        $changed['title'] = 'Unpublished draft title';
        $changed['slug'] = 'published-edit-draft';
        $changed['sections'][0]['content'] = 'Only the draft may see this text.';

        $this->actingAs($this->admin())
            ->patchJson(route('admin.api.pages.draft.update', $page), [
                'lockVersion' => 1,
                'document' => $changed,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.hasPublishedSnapshot', true)
            ->assertJsonPath('data.hasUnpublishedChanges', true)
            ->assertJsonPath('data.document.title', 'Unpublished draft title');

        $fresh = $page->fresh();
        $public = app(PageRuntimePayloadFactory::class)->forCanary($fresh);

        $this->assertSame('Published title', $public['page']['document']['title']);
        $this->assertSame('published-edit', $public['page']['document']['slug']);
        $this->assertSame($publishedValues, $fresh->publishedDocument->only(array_keys($publishedValues)));
        $this->assertSame('Unpublished draft title', $fresh->draftDocument->document['title']);
        $this->assertSame('published-edit-draft', $fresh->draftDocument->slug);
    }

    public function test_publish_promotes_complete_draft_atomically(): void
    {
        $page = $this->canonicalPage($this->fullFixture('complete-promotion'), lockVersion: 7);
        $draftBefore = $page->draftDocument;

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 7])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.hasPublishedSnapshot', true)
            ->assertJsonPath('data.hasUnpublishedChanges', false);

        $fresh = $page->fresh();
        $published = $fresh->publishedDocument;

        $this->assertSame($draftBefore->slug, $published->slug);
        $this->assertSame($draftBefore->schema_version, $published->schema_version);
        $this->assertSame($draftBefore->lock_version, $published->lock_version);
        $this->assertSame($draftBefore->content_hash, $published->content_hash);
        $this->assertSame($draftBefore->document, $published->document);
        $this->assertSame($published->content_hash, PageDocumentHasher::hash($published->document));
        $this->assertNotNull($fresh->published_at);
        $this->assertNull($fresh->archived_at);
        $this->assertSame($published->content_hash, $response->json('data.hasUnpublishedChanges') === false
            ? $fresh->draftDocument->content_hash
            : null);

        $public = app(PageRuntimePayloadFactory::class)->forCanary($fresh);
        $this->assertSame($draftBefore->document, $public['page']['document']);
        $this->assertSame($draftBefore->content_hash, $public['page']['contentHash']);
    }

    public function test_unpublish_and_archive_keep_both_documents_but_return_public_404(): void
    {
        $document = $this->document('retained-snapshots', 'Retained snapshots');
        $page = $this->canonicalPage($document, status: 'published', publishedDocument: $document);
        $draftBefore = $page->draftDocument->only(['slug', 'content_hash', 'document']);
        $publishedBefore = $page->publishedDocument->only(['slug', 'content_hash', 'document']);

        app(PageRuntimePayloadFactory::class)->forCanary($page);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.unpublish', $page))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.hasPublishedSnapshot', true)
            ->assertJsonPath('data.hasUnpublishedChanges', false);

        $unpublished = $page->fresh();
        $this->assertNull($unpublished->archived_at);
        $this->assertSame($draftBefore, $unpublished->draftDocument->only(array_keys($draftBefore)));
        $this->assertSame($publishedBefore, $unpublished->publishedDocument->only(array_keys($publishedBefore)));
        $this->assertPublishedUnavailable($unpublished);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.archive', $page))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.hasPublishedSnapshot', true)
            ->assertJsonPath('data.hasUnpublishedChanges', false);

        $archived = $page->fresh();
        $this->assertNotNull($archived->archived_at);
        $this->assertSame($draftBefore, $archived->draftDocument->only(array_keys($draftBefore)));
        $this->assertSame($publishedBefore, $archived->publishedDocument->only(array_keys($publishedBefore)));
        $this->assertDatabaseCount('page_documents', 2);
        $this->assertPublishedUnavailable($archived);
    }

    public function test_republish_from_archived_uses_current_draft(): void
    {
        $oldPublished = $this->document('archived-old', 'Old published title');
        $currentDraft = $this->document('archived-current', 'Current draft title');
        $page = $this->canonicalPage(
            $currentDraft,
            status: 'archived',
            lockVersion: 4,
            publishedDocument: $oldPublished,
        );

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 4])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.title', 'Current draft title')
            ->assertJsonPath('data.slug', 'archived-current')
            ->assertJsonPath('data.hasUnpublishedChanges', false);

        $fresh = $page->fresh();

        $this->assertNull($fresh->archived_at);
        $this->assertNotNull($fresh->published_at);
        $this->assertSame($fresh->draftDocument->document, $fresh->publishedDocument->document);
        $this->assertSame('Current draft title', $fresh->publishedDocument->document['title']);
        $this->assertSame(4, $fresh->publishedDocument->lock_version);
        $this->assertSame('Current draft title', app(PageRuntimePayloadFactory::class)
            ->forCanary($fresh)['page']['document']['title']);
    }

    public function test_first_publish_slug_collision_returns_stable_422_without_mutating_either_page(): void
    {
        $owner = $this->canonicalPage(
            $this->document('published-owner-draft', 'Published owner draft'),
            status: 'published',
            publishedDocument: $this->document('contested-published-slug', 'Published owner snapshot'),
        );
        $candidate = $this->canonicalPage(
            $this->document('contested-published-slug', 'First publish candidate'),
            lockVersion: 5,
        );
        $candidateLifecycleBefore = $this->lifecycleState($candidate);
        $candidateDraftBefore = $this->documentState($candidate->draftDocument);
        $ownerLifecycleBefore = $this->lifecycleState($owner);
        $ownerDraftBefore = $this->documentState($owner->draftDocument);
        $ownerPublishedBefore = $this->documentState($owner->publishedDocument);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $candidate), ['lockVersion' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_publication_blocked');

        $this->assertSame(
            ['This slug is already used by another published page.'],
            $response->json('errors')['slug'],
        );
        $this->assertSame($candidateLifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($candidateDraftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertSame($ownerLifecycleBefore, $this->lifecycleState($owner));
        $this->assertSame($ownerDraftBefore, $this->documentState($owner->fresh()->draftDocument));
        $this->assertSame($ownerPublishedBefore, $this->documentState($owner->fresh()->publishedDocument));
        $this->assertDatabaseCount('page_documents', 3);
    }

    public function test_republish_rename_slug_collision_keeps_old_snapshot_and_both_pages_unchanged(): void
    {
        $owner = $this->canonicalPage(
            $this->document('rename-owner-draft', 'Rename owner draft'),
            status: 'published',
            publishedDocument: $this->document('contested-rename-slug', 'Rename owner snapshot'),
        );
        $candidate = $this->canonicalPage(
            $this->document('contested-rename-slug', 'Renamed current draft'),
            status: 'archived',
            lockVersion: 8,
            publishedDocument: $this->document('candidate-old-live-slug', 'Candidate old snapshot'),
        );
        $candidateLifecycleBefore = $this->lifecycleState($candidate);
        $candidateDraftBefore = $this->documentState($candidate->draftDocument);
        $candidatePublishedBefore = $this->documentState($candidate->publishedDocument);
        $ownerLifecycleBefore = $this->lifecycleState($owner);
        $ownerDraftBefore = $this->documentState($owner->draftDocument);
        $ownerPublishedBefore = $this->documentState($owner->publishedDocument);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $candidate), ['lockVersion' => 8])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_publication_blocked');

        $this->assertSame(
            ['This slug is already used by another published page.'],
            $response->json('errors')['slug'],
        );
        $this->assertSame($candidateLifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($candidateDraftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertSame($candidatePublishedBefore, $this->documentState($candidate->fresh()->publishedDocument));
        $this->assertSame($ownerLifecycleBefore, $this->lifecycleState($owner));
        $this->assertSame($ownerDraftBefore, $this->documentState($owner->fresh()->draftDocument));
        $this->assertSame($ownerPublishedBefore, $this->documentState($owner->fresh()->publishedDocument));
        $this->assertDatabaseCount('page_documents', 4);
    }

    public function test_published_slug_unique_race_is_translated_after_preflight_and_rolls_back(): void
    {
        $raceOwner = $this->canonicalPage($this->document('race-owner-draft', 'Race owner'));
        $candidate = $this->canonicalPage(
            $this->document('published-race-slug', 'Published race candidate'),
            lockVersion: 6,
        );
        $candidateLifecycleBefore = $this->lifecycleState($candidate);
        $candidateDraftBefore = $this->documentState($candidate->draftDocument);
        $raceOwnerDraftBefore = $this->documentState($raceOwner->draftDocument);
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($raceOwner): void {
            if ($publishing->stage !== PageDocument::STAGE_PUBLISHED
                || $publishing->slug !== 'published-race-slug') {
                return;
            }

            DB::table('page_documents')->insert([
                'page_id' => $raceOwner->getKey(),
                'stage' => PageDocument::STAGE_PUBLISHED,
                'slug' => $publishing->slug,
                'schema_version' => $publishing->schema_version,
                'lock_version' => $publishing->lock_version,
                'content_hash' => $publishing->content_hash,
                'document' => $publishing->getAttributes()['document'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $response = $this->actingAs($this->admin())
                ->postJson(route('admin.api.pages.publish', $candidate), ['lockVersion' => 6]);
        } finally {
            Event::forget($event);
        }

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_publication_blocked');
        $this->assertSame(
            ['This slug is already used by another published page.'],
            $response->json('errors')['slug'],
        );
        $this->assertSame($candidateLifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($candidateDraftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertSame($raceOwnerDraftBefore, $this->documentState($raceOwner->fresh()->draftDocument));
        $this->assertNull($raceOwner->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 2);
    }

    public function test_unrelated_publication_query_failure_propagates_and_rolls_back(): void
    {
        $candidate = $this->canonicalPage(
            $this->document('unrelated-query-failure', 'Unrelated query failure'),
            lockVersion: 4,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                DB::statement('insert into cms_publication_missing_table (id) values (1)');
            }
        });

        try {
            app(PagePublicationService::class)->publish($candidate, 4);
            $this->fail('Expected the unrelated database failure to propagate.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('cms_publication_missing_table', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    public function test_mysql_index_name_without_duplicate_semantics_propagates_and_rolls_back(): void
    {
        $candidate = $this->canonicalPage(
            $this->document('mysql-ddl-failure', 'MySQL DDL failure'),
            lockVersion: 11,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $injected = $this->driverQueryException(
            driver: 'mysql',
            sqlState: '42000',
            driverCode: 1064,
            driverMessage: 'Syntax error while inspecting page_documents_stage_slug_unique.',
            sql: 'alter table page_documents add constraint page_documents_stage_slug_unique',
        );
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($injected): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                throw $injected;
            }
        });

        try {
            app(PagePublicationService::class)->publish($candidate, 11);
            $this->fail('Expected the unrelated MySQL DDL failure to propagate.');
        } catch (QueryException $exception) {
            $this->assertSame($injected, $exception);
        } finally {
            Event::forget($event);
        }

        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    public function test_sqlite_target_text_without_unique_integrity_semantics_propagates_and_rolls_back(): void
    {
        $candidate = $this->canonicalPage(
            $this->document('sqlite-message-coincidence', 'SQLite message coincidence'),
            lockVersion: 12,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $injected = $this->driverQueryException(
            driver: 'sqlite',
            sqlState: 'HY000',
            driverCode: 1,
            driverMessage: 'Malformed DDL note: UNIQUE constraint failed: page_documents.stage, page_documents.slug.',
        );
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($injected): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                throw $injected;
            }
        });

        try {
            app(PagePublicationService::class)->publish($candidate, 12);
            $this->fail('Expected the unrelated SQLite failure to propagate.');
        } catch (QueryException $exception) {
            $this->assertSame($injected, $exception);
        } finally {
            Event::forget($event);
        }

        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    public function test_mysql_target_duplicate_signature_is_translated_to_stable_publication_error(): void
    {
        $candidate = $this->canonicalPage(
            $this->document('mysql-target-duplicate', 'MySQL target duplicate'),
            lockVersion: 13,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $injected = $this->driverQueryException(
            driver: 'mysql',
            sqlState: '23000',
            driverCode: 1062,
            driverMessage: "Duplicate entry 'mysql-target-duplicate' for key 'page_documents_stage_slug_unique'.",
        );
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($injected): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                throw $injected;
            }
        });

        try {
            $response = $this->actingAs($this->admin())
                ->postJson(route('admin.api.pages.publish', $candidate), ['lockVersion' => 13]);
        } finally {
            Event::forget($event);
        }

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_publication_blocked');
        $this->assertSame(
            ['This slug is already used by another published page.'],
            $response->json('errors')['slug'],
        );
        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
    }

    public function test_mysql_non_target_duplicate_constraint_propagates_and_rolls_back(): void
    {
        $candidate = $this->canonicalPage(
            $this->document('mysql-other-duplicate', 'MySQL other duplicate'),
            lockVersion: 14,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $injected = $this->driverQueryException(
            driver: 'mysql',
            sqlState: '23000',
            driverCode: 1062,
            driverMessage: "Duplicate entry '1-published' for key 'page_documents_page_id_stage_unique'.",
        );
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($injected): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                throw $injected;
            }
        });

        try {
            app(PagePublicationService::class)->publish($candidate, 14);
            $this->fail('Expected the non-target MySQL duplicate to propagate.');
        } catch (QueryException $exception) {
            $this->assertSame($injected, $exception);
        } finally {
            Event::forget($event);
        }

        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    public function test_mysql_shadow_target_identifier_propagates_and_rolls_back(): void
    {
        $this->assertInjectedDriverDuplicatePropagates(
            driver: 'mysql',
            driverMessage: "Duplicate entry 'mysql-shadow' for key 'page_documents_stage_slug_unique_shadow'",
            slug: 'mysql-shadow-target',
            lockVersion: 15,
        );
    }

    public function test_mysql_qualified_exact_target_identifier_is_translated(): void
    {
        $this->assertInjectedDriverDuplicateIsTranslated(
            driver: 'mysql',
            driverMessage: "Duplicate entry 'mysql-qualified' for key 'jw_test.page_documents.page_documents_stage_slug_unique'",
            slug: 'mysql-qualified-target',
            lockVersion: 16,
        );
    }

    public function test_mariadb_backtick_qualified_exact_target_identifier_is_translated(): void
    {
        $this->assertInjectedDriverDuplicateIsTranslated(
            driver: 'mariadb',
            driverMessage: "Duplicate entry 'mariadb-target' for key `jw_test`.`page_documents`.`page_documents_stage_slug_unique`",
            slug: 'mariadb-exact-target',
            lockVersion: 17,
        );
    }

    public function test_mariadb_near_match_identifier_propagates_and_rolls_back(): void
    {
        $this->assertInjectedDriverDuplicatePropagates(
            driver: 'mariadb',
            driverMessage: "Duplicate entry 'mariadb-shadow' for key `page_documents_stage_slug_unique_shadow`",
            slug: 'mariadb-near-match',
            lockVersion: 18,
        );
    }

    public function test_sqlite_exact_target_column_list_is_translated(): void
    {
        $this->assertInjectedDriverDuplicateIsTranslated(
            driver: 'sqlite',
            driverMessage: 'UNIQUE constraint failed: page_documents.stage, page_documents.slug',
            slug: 'sqlite-exact-target',
            lockVersion: 19,
            driverCode: 19,
        );
    }

    public function test_sqlite_target_column_list_with_third_column_propagates_and_rolls_back(): void
    {
        $this->assertInjectedDriverDuplicatePropagates(
            driver: 'sqlite',
            driverMessage: 'UNIQUE constraint failed: page_documents.stage, page_documents.slug, page_documents.locale',
            slug: 'sqlite-third-column',
            lockVersion: 20,
            driverCode: 2067,
        );
    }

    public function test_sqlite_near_match_column_names_propagate_and_roll_back(): void
    {
        $this->assertInjectedDriverDuplicatePropagates(
            driver: 'sqlite',
            driverMessage: 'UNIQUE constraint failed: page_documents.stage_shadow, page_documents.slug_shadow',
            slug: 'sqlite-near-columns',
            lockVersion: 21,
            driverCode: 19,
        );
    }

    public function test_hidden_unsupported_section_does_not_block_publish(): void
    {
        $document = $this->document('hidden-unsupported', 'Visible hero', [
            $this->heroSection('visible-hero', 'Visible hero'),
            [
                'id' => 'hidden-split-hero',
                'type' => 'split_hero',
                'title' => '',
                'isVisible' => false,
            ],
        ]);
        $page = $this->canonicalPage($document);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.sections.1.type', 'split_hero')
            ->assertJsonPath('data.sections.1.isVisible', false);

        $this->assertSame($page->draftDocument->document, $page->fresh()->publishedDocument->document);
    }

    public function test_visible_adapter_invalid_section_blocks_publish_with_field_path(): void
    {
        $document = $this->document('runtime-invalid', 'Runtime invalid', [[
            'id' => 'invalid-product-grid',
            'type' => 'product_grid',
            'title' => 'Missing products',
            'isVisible' => true,
            'productIds' => ['999999'],
        ]]);
        $page = $this->canonicalPage($document);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_runtime_reference_invalid');

        $this->assertArrayNotHasKey('document', $response->json());
        $this->assertSame(
            ['The runtime reference for this visible section is invalid.'],
            $response->json('errors')['sections.0.productIds'],
        );
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->publishedDocument);
        $this->assertSame($document, $page->fresh()->draftDocument->document);
    }

    public function test_visible_required_field_failures_use_publication_blocked_code_and_exact_paths(): void
    {
        $document = $this->document('publication-blocked', 'Publication blocked', [
            $this->heroSection('missing-required-title', ''),
        ]);
        $page = $this->canonicalPage($document);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_publication_blocked');

        $this->assertSame(
            ['This visible section field is required to publish.'],
            $response->json('errors')['sections.0.title'],
        );

        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->publishedDocument);
    }

    public function test_stale_publish_returns_existing_conflict_shape_without_replacement_document_or_mutation(): void
    {
        $document = $this->document('stale-publish', 'Stale publish');
        $page = $this->canonicalPage($document, lockVersion: 9);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), ['lockVersion' => 8])
            ->assertConflict()
            ->assertJsonPath('message', 'The page draft was changed by another session.')
            ->assertJsonPath('code', 'cms_version_conflict')
            ->assertJsonPath(
                'errors.lockVersion.0',
                'Reload or reconcile your local changes before saving again.',
            )
            ->assertJsonPath('currentLockVersion', 9);

        $this->assertArrayNotHasKey('document', $response->json());
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->publishedDocument);
        $this->assertSame(9, $page->fresh()->draftDocument->lock_version);
        $this->assertSame($document, $page->fresh()->draftDocument->document);
    }

    public function test_publish_requires_a_lock_version_with_stable_validation_shape(): void
    {
        $page = $this->canonicalPage($this->document('missing-lock', 'Missing lock'));

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.publish', $page), [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page publication request is invalid.')
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath('errors.lockVersion.0', 'The lock version field is required.');

        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->publishedDocument);
    }

    public function test_publication_transaction_rolls_back_snapshot_replacement_when_page_transition_fails(): void
    {
        $oldPublished = $this->document('rollback-old', 'Old published snapshot');
        $currentDraft = $this->document('rollback-current', 'Current draft snapshot');
        $page = $this->canonicalPage(
            $currentDraft,
            status: 'archived',
            lockVersion: 3,
            publishedDocument: $oldPublished,
        );
        $before = $page->fresh();
        $publishedBefore = $before->publishedDocument->only([
            'slug',
            'schema_version',
            'lock_version',
            'content_hash',
            'document',
        ]);
        $archivedAtBefore = $before->archived_at?->toISOString();
        $event = 'eloquent.updating: '.Page::class;

        Event::listen($event, static function (Page $updating): void {
            if ($updating->status === 'published') {
                throw new RuntimeException('Injected page transition failure.');
            }
        });

        try {
            app(PagePublicationService::class)->publish($page, 3);
            $this->fail('Expected the injected lifecycle transition failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected page transition failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $rolledBack = $page->fresh();

        $this->assertSame('archived', $rolledBack->status);
        $this->assertSame($archivedAtBefore, $rolledBack->archived_at?->toISOString());
        $this->assertSame($publishedBefore, $rolledBack->publishedDocument->only(array_keys($publishedBefore)));
        $this->assertSame('Current draft snapshot', $rolledBack->draftDocument->document['title']);
        $this->assertDatabaseCount('page_documents', 2);
    }

    public function test_unpublish_and_archive_fail_without_mutating_a_page_that_has_no_canonical_draft(): void
    {
        $publishedLegacyOnly = Page::query()->create([
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $draftLegacyOnly = Page::query()->create(['status' => 'draft']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('admin.api.pages.unpublish', $publishedLegacyOnly))
            ->assertNotFound();
        $this->actingAs($admin)
            ->postJson(route('admin.api.pages.archive', $draftLegacyOnly))
            ->assertNotFound();

        $this->assertSame('published', $publishedLegacyOnly->fresh()->status);
        $this->assertNotNull($publishedLegacyOnly->fresh()->published_at);
        $this->assertSame('draft', $draftLegacyOnly->fresh()->status);
        $this->assertNull($draftLegacyOnly->fresh()->archived_at);
        $this->assertDatabaseCount('page_documents', 0);
    }

    public function test_lifecycle_endpoints_are_protected_by_web_auth_and_admin_without_mutation(): void
    {
        $page = $this->canonicalPage($this->document('protected-lifecycle', 'Protected lifecycle'));
        $requests = [
            ['admin.api.pages.publish', ['lockVersion' => 1]],
            ['admin.api.pages.unpublish', []],
            ['admin.api.pages.archive', []],
        ];

        foreach ($requests as [$routeName, $payload]) {
            $this->postJson(route($routeName, $page), $payload)->assertUnauthorized();
            $this->actingAs(User::factory()->create(['is_admin' => false]))
                ->postJson(route($routeName, $page), $payload)
                ->assertUnauthorized();
        }

        foreach (['publish', 'unpublish', 'archive'] as $action) {
            $route = Route::getRoutes()->getByName("admin.api.pages.{$action}");
            $this->assertNotNull($route);
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('admin', $route->gatherMiddleware());
        }

        $fresh = $page->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertNull($fresh->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $sections
     * @return array<string, mixed>
     */
    private function document(string $slug, string $title, ?array $sections = null): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'description' => 'Canonical publication fixture.',
            'template' => 'landing',
            'heroImage' => null,
            'seoTitle' => $title.' | JW Studio',
            'seoDescription' => 'Publication lifecycle proof.',
            'sections' => $sections ?? [$this->heroSection('hero-section', $title)],
        ];
    }

    /** @return array<string, mixed> */
    private function heroSection(string $id, string $title): array
    {
        return [
            'id' => $id,
            'type' => 'hero_banner',
            'title' => $title,
            'subtitle' => '',
            'content' => 'Canonical hero content.',
            'buttonText' => '',
            'buttonLink' => '',
            'overlayOpacity' => 0,
            'items' => [],
            'isVisible' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function fullFixture(string $slug): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json'));

        if ($contents === false) {
            $this->fail('CMS contract fixture is unreadable.');
        }

        try {
            $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('CMS contract fixture is invalid: '.$exception->getMessage());
        }

        $document['slug'] = $slug;

        foreach ($document['sections'] as $index => &$section) {
            $section['isVisible'] = $index === 0;
        }
        unset($section);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $draftDocument
     * @param  array<string, mixed>|null  $publishedDocument
     */
    private function canonicalPage(
        array $draftDocument,
        string $status = 'draft',
        int $lockVersion = 1,
        ?array $publishedDocument = null,
    ): Page {
        $contract = app(PageDocumentContract::class);
        $codec = app(PageDocumentJsonCodec::class);
        $draftDocument = $codec->canonicalizeDocument($contract->validate($draftDocument));
        $publishedDocument = $publishedDocument === null
            ? null
            : $codec->canonicalizeDocument($contract->validate($publishedDocument));
        $page = Page::query()->create([
            'status' => $status,
            'published_at' => in_array($status, ['published', 'archived'], true) ? now()->subDay() : null,
            'archived_at' => $status === 'archived' ? now()->subHour() : null,
        ]);

        $this->storeDocument($page, PageDocument::STAGE_DRAFT, $draftDocument, $lockVersion);

        if ($publishedDocument !== null) {
            $this->storeDocument($page, PageDocument::STAGE_PUBLISHED, $publishedDocument, 1);
        }

        return $page->fresh();
    }

    /** @param array<string, mixed> $document */
    private function storeDocument(Page $page, string $stage, array $document, int $lockVersion): void
    {
        $page->documents()->create([
            'stage' => $stage,
            'slug' => $document['slug'],
            'schema_version' => PageDocumentContract::VERSION,
            'lock_version' => $lockVersion,
            'content_hash' => PageDocumentHasher::hash($document),
            'document' => $document,
        ]);
    }

    private function assertPublishedUnavailable(Page $page): void
    {
        try {
            app(PageRuntimePayloadFactory::class)->forCanary($page);
            $this->fail('Expected the non-published lifecycle to return runtime 404.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    /** @return array<string, mixed> */
    private function lifecycleState(Page $page): array
    {
        $page = $page->fresh();

        return [
            'status' => $page->status,
            'publishedAt' => $page->published_at?->toISOString(),
            'archivedAt' => $page->archived_at?->toISOString(),
            'updatedAt' => $page->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function documentState(PageDocument $document): array
    {
        $document = $document->fresh();

        return [
            'id' => $document->getKey(),
            'pageId' => $document->page_id,
            'stage' => $document->stage,
            'slug' => $document->slug,
            'schemaVersion' => $document->schema_version,
            'lockVersion' => $document->lock_version,
            'contentHash' => $document->content_hash,
            'document' => $document->document,
            'updatedAt' => $document->updated_at?->toISOString(),
        ];
    }

    private function driverQueryException(
        string $driver,
        string $sqlState,
        int $driverCode,
        string $driverMessage,
        string $sql = 'insert into page_documents values (?)',
    ): QueryException {
        $previous = new PDOException($driverMessage);
        $previous->errorInfo = [$sqlState, $driverCode, $driverMessage];

        return new QueryException(
            $driver,
            $sql,
            [],
            $previous,
            ['driver' => $driver, 'database' => ':memory:'],
            'write',
        );
    }

    private function assertInjectedDriverDuplicateIsTranslated(
        string $driver,
        string $driverMessage,
        string $slug,
        int $lockVersion,
        int $driverCode = 1062,
    ): void {
        $candidate = $this->canonicalPage(
            $this->document($slug, 'Exact target duplicate'),
            lockVersion: $lockVersion,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $injected = $this->driverQueryException(
            driver: $driver,
            sqlState: '23000',
            driverCode: $driverCode,
            driverMessage: $driverMessage,
        );
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($injected): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                throw $injected;
            }
        });

        try {
            $response = $this->actingAs($this->admin())
                ->postJson(route('admin.api.pages.publish', $candidate), ['lockVersion' => $lockVersion]);
        } finally {
            Event::forget($event);
        }

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The page cannot be published.')
            ->assertJsonPath('code', 'cms_publication_blocked');
        $this->assertSame(
            ['This slug is already used by another published page.'],
            $response->json('errors')['slug'],
        );
        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    private function assertInjectedDriverDuplicatePropagates(
        string $driver,
        string $driverMessage,
        string $slug,
        int $lockVersion,
        int $driverCode = 1062,
    ): void {
        $candidate = $this->canonicalPage(
            $this->document($slug, 'Near-match duplicate'),
            lockVersion: $lockVersion,
        );
        $lifecycleBefore = $this->lifecycleState($candidate);
        $draftBefore = $this->documentState($candidate->draftDocument);
        $injected = $this->driverQueryException(
            driver: $driver,
            sqlState: '23000',
            driverCode: $driverCode,
            driverMessage: $driverMessage,
        );
        $event = 'eloquent.creating: '.PageDocument::class;

        Event::listen($event, static function (PageDocument $publishing) use ($injected): void {
            if ($publishing->stage === PageDocument::STAGE_PUBLISHED) {
                throw $injected;
            }
        });

        try {
            app(PagePublicationService::class)->publish($candidate, $lockVersion);
            $this->fail('Expected the near-match duplicate constraint to propagate.');
        } catch (QueryException $exception) {
            $this->assertSame($injected, $exception);
        } finally {
            Event::forget($event);
        }

        $this->assertSame($lifecycleBefore, $this->lifecycleState($candidate));
        $this->assertSame($draftBefore, $this->documentState($candidate->fresh()->draftDocument));
        $this->assertNull($candidate->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }
}
