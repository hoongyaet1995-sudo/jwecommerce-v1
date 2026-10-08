<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use JsonException;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class PageDraftApiTest extends TestCase
{
    use RefreshDatabase;
    public function test_standalone_values_list_is_saved_on_an_unrelated_page_with_order_and_style_intact(): void
    {
        $document = $this->fixture('standalone-values-qa');
        $document['sections'] = [['id' => 'v', 'type' => 'values_list', 'title' => 'Stored heading', 'isVisible' => true, 'valuesShowHeading' => false, 'valuesShowDividers' => true, 'valuesRowSpacing' => 'spacious', 'valuesNumberColor' => '#bbbbbb', 'items' => [['id' => 'one', 'title' => 'First', 'description' => 'One'], ['id' => 'two', 'title' => 'Second', 'description' => 'Two']]]];
        $page = $this->canonicalPage($document);
        $this->actingAs($this->admin())->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 1, 'document' => $document])->assertOk();
        $document['sections'][0]['items'] = array_reverse($document['sections'][0]['items']);
        $this->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 2, 'document' => $document])->assertOk();
        $this->assertSame($document, $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document'));
    }

    public function test_values_list_reordering_and_preset_switch_preserve_item_identity_and_legacy_fields(): void
    {
        $document = $this->fixture('generic-values-qa');
        $document['sections'] = [['id' => 'values', 'type' => 'feature_cards', 'title' => 'Stored heading', 'isVisible' => true, 'featureCardsPreset' => 'values-list', 'valuesShowHeading' => false, 'items' => [['id' => 'one', 'title' => 'First', 'description' => 'One', 'icon' => 'Sparkles', 'badge' => 'Saved badge'], ['id' => 'two', 'title' => 'Second', 'description' => 'Two', 'icon' => 'ShieldCheck']]]];
        $page = $this->canonicalPage($document);
        $this->actingAs($this->admin())->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 1, 'document' => $document])->assertOk();
        $document['sections'][0]['items'] = array_reverse($document['sections'][0]['items']);
        $document['sections'][0]['featureCardsPreset'] = 'default';
        $this->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 2, 'document' => $document])->assertOk();
        $this->assertSame($document, $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document'));
    }

    public function test_manifesto_round_trips_on_a_generic_page_and_preserves_quote_on_preset_switch(): void
    {
        $document = $this->fixture('generic-manifesto-qa');
        $document['sections'] = [['id' => 'statement', 'type' => 'rich_text', 'title' => 'Saved statement', 'isVisible' => true, 'richTextPreset' => 'manifesto', 'content' => "First.\n\nSecond.", 'quote' => 'Preserved quote', 'authorName' => 'Author', 'paddingY' => 'spacious']];
        $page = $this->canonicalPage($document);
        $this->actingAs($this->admin())->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 1, 'document' => $document])->assertOk();
        $this->assertSame($document, $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document'));
        $document['sections'][0]['richTextPreset'] = 'default';
        $this->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 2, 'document' => $document])->assertOk();
        $this->assertSame('Preserved quote', $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document.sections.0.quote'));
    }

    public function test_gallery_image_targets_round_trip_independently_after_reordering(): void
    {
        $document = $this->fixture('image-targets-qa');
        $document['sections'] = [[
            'id' => 'gallery', 'type' => 'media_gallery', 'title' => 'Gallery', 'isVisible' => true,
            'items' => [['id' => 'one', 'title' => 'One', 'imageUrl' => ''], ['id' => 'two', 'title' => 'Two', 'imageUrl' => '']],
            'imageSlots' => [['target' => 'item:one:imageUrl', 'imageRatio' => '1:1', 'imagePosition' => 'bottom-right'], ['target' => 'item:two:imageUrl', 'imageShape' => 'rounded', 'imageOverlay' => true, 'imageOverlayOpacity' => 40]],
        ]];
        $page = $this->canonicalPage($document);
        $this->actingAs($this->admin())->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 1, 'document' => $document])->assertOk();
        $document['sections'][0]['items'] = array_reverse($document['sections'][0]['items']);
        $this->patchJson(route('admin.api.pages.draft.update', $page), ['lockVersion' => 2, 'document' => $document])->assertOk();
        $this->assertSame($document, $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document'));
    }

    public function test_editorial_story_settings_round_trip_on_an_unrelated_page(): void
    {
        $document = $this->fixture('generic-section-reuse-qa');
        $document['title'] = 'Generic section reuse QA';
        $document['sections'] = [[
            'id' => 'story', 'type' => 'media_text_split', 'title' => 'Reusable story', 'isVisible' => true,
            'layoutPreset' => 'editorial-story', 'content' => "First paragraph.\n\nSecond paragraph.",
            'mediaPosition' => 'right', 'mediaAspectRatio' => '16:9', 'mediaAlt' => '', 'mediaFocalPoint' => 'bottom',
            'buttonStyle' => 'text-link', 'buttonText' => 'View collection', 'buttonLink' => '/shop',
            'imageRatio' => '3:4', 'imagePosition' => 'bottom-right', 'imageFit' => 'contain',
            'imageWidth' => '75', 'imageShape' => 'rounded', 'imageOverlay' => true, 'imageOverlayOpacity' => 40,
            'imageForeground' => true, 'imageForegroundColor' => '#ff0000', 'imageForegroundOpacity' => 20,
        ]];
        $page = $this->canonicalPage($document);
        $this->actingAs($this->admin())->patchJson(route('admin.api.pages.draft.update', $page), [
            'lockVersion' => 1, 'document' => $document,
        ])->assertOk();
        $this->assertSame($document, $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document'));
    }

    public function test_editorial_hero_settings_survive_a_saved_draft_reload(): void
    {
        $document = $this->fixture('editorial-hero-draft');
        $document['sections'] = [[
            'id' => 'editorial-hero', 'type' => 'media_text_split', 'title' => "A closer look\nat JW Studio.",
            'isVisible' => true, 'layoutPreset' => 'editorial-hero', 'mediaAlt' => '', 'mediaFocalPoint' => 'top',
        ]];
        $page = $this->canonicalPage($document);
        $document['sections'][0]['mediaAlt'] = 'Illustrative writing desk';
        $this->actingAs($this->admin())->patchJson(route('admin.api.pages.draft.update', $page), [
            'lockVersion' => 1, 'document' => $document,
        ])->assertOk();
        $saved = $this->getJson(route('admin.api.pages.draft.show', $page))->assertOk()->json('data.document');
        $this->assertSame($document, $saved);
    }

    public function test_admin_can_read_only_the_canonical_draft_identity_and_sections(): void
    {
        $document = $this->fixture('canonical-page');
        $page = $this->canonicalPage($document, legacyIdentity: true);
        $page->sections()->create([
            'type' => 'rich_text',
            'content' => [
                'title' => 'Legacy section must not be read',
                'isVisible' => true,
            ],
            'position' => 0,
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.draft.show', $page))
            ->assertOk()
            ->assertJsonPath('data.pageId', (string) $page->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.document.slug', 'canonical-page')
            ->assertJsonPath('data.document.sections.0.id', 'sec-hero-banner')
            ->assertJsonPath('data.lockVersion', 1)
            ->assertJsonPath('data.hasPublishedSnapshot', false)
            ->assertJsonPath('data.hasUnpublishedChanges', true);

        $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Contract Fixture')
            ->assertJsonPath('data.0.slug', 'canonical-page')
            ->assertJsonPath('data.0.sections.0.id', 'sec-hero-banner')
            ->assertJsonMissing(['Legacy page title', 'legacy-page-slug', 'Legacy section must not be read']);
    }

    public function test_list_ignores_a_legacy_only_page_without_creating_a_canonical_document(): void
    {
        $canonical = $this->canonicalPage($this->fixture('canonical-list-page'));
        $legacyOnly = Page::query()->create([
            'slug' => 'legacy-only',
            'title' => 'Legacy only page',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.index'))
            ->assertOk();

        $this->assertSame([(string) $canonical->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertDatabaseMissing('page_documents', ['page_id' => $legacyOnly->id]);
    }

    public function test_draft_save_round_trips_all_section_fields_and_atomically_advances_version(): void
    {
        $original = $this->fixture('lossless-page');
        $page = $this->canonicalPage($original);
        $changed = $original;
        $changed['description'] = 'All contract fields survive the save.';

        $response = $this->actingAs($this->admin())->patchJson(
            route('admin.api.pages.draft.update', $page),
            ['lockVersion' => 1, 'document' => $changed],
        )->assertOk();

        $response
            ->assertJsonPath('data.document.sections.0.subtitle', '')
            ->assertJsonPath('data.document.sections.0.overlayOpacity', 0)
            ->assertJsonPath('data.document.sections.1.sliderAutoplay', false)
            ->assertJsonPath('data.document.sections.1.items.0.features', [])
            ->assertJsonPath('data.lockVersion', 2)
            ->assertJsonPath('data.contentHash', PageDocumentHasher::hash($changed));

        $draft = $page->fresh()->draftDocument;

        $this->assertSame($changed, $draft->document);
        $this->assertSame('lossless-page', $draft->slug);
        $this->assertSame(2, $draft->lock_version);
        $this->assertSame(PageDocumentHasher::hash($changed), $draft->content_hash);
    }

    public function test_stale_draft_save_returns_current_version_without_overwriting_or_returning_server_document(): void
    {
        $original = $this->fixture('conflict-page');
        $page = $this->canonicalPage($original, lockVersion: 7);
        $changed = $original;
        $changed['title'] = 'Stale client title';
        $changed['slug'] = 'stale-client-slug';

        $response = $this->actingAs($this->admin())->patchJson(
            route('admin.api.pages.draft.update', $page),
            ['lockVersion' => 6, 'document' => $changed],
        )->assertConflict()
            ->assertJsonPath('message', 'The page draft was changed by another session.')
            ->assertJsonPath('code', 'cms_version_conflict')
            ->assertJsonPath('errors.lockVersion.0', 'Reload or reconcile your local changes before saving again.')
            ->assertJsonPath('currentLockVersion', 7);

        $this->assertArrayNotHasKey('document', $response->json());

        $draft = $page->fresh()->draftDocument;

        $this->assertSame($original, $draft->document);
        $this->assertSame('conflict-page', $draft->slug);
        $this->assertSame(7, $draft->lock_version);
        $this->assertSame(PageDocumentHasher::hash($original), $draft->content_hash);
    }

    public function test_unsupported_contract_field_returns_stable_422_without_mutation(): void
    {
        $original = $this->fixture('validation-page');
        $page = $this->canonicalPage($original);
        $invalid = $original;
        $invalid['sections'][0]['inventedSetting'] = 'must not be dropped silently';

        $response = $this->actingAs($this->admin())->patchJson(
            route('admin.api.pages.draft.update', $page),
            ['lockVersion' => 1, 'document' => $invalid],
        )->assertUnprocessable()
            ->assertJsonPath('message', 'The page document is invalid.')
            ->assertJsonPath('code', 'unsupported_contract_field');

        $this->assertSame(
            ['This field is not supported by the page contract.'],
            $response->json('errors')['sections.0.inventedSetting'],
        );

        $draft = $page->fresh()->draftDocument;

        $this->assertSame($original, $draft->document);
        $this->assertSame(1, $draft->lock_version);
        $this->assertSame(PageDocumentHasher::hash($original), $draft->content_hash);
    }

    public function test_malformed_draft_request_returns_stable_422_without_mutation(): void
    {
        $original = $this->fixture('request-validation-page');
        $page = $this->canonicalPage($original);

        $this->actingAs($this->admin())->patchJson(
            route('admin.api.pages.draft.update', $page),
            ['document' => $original],
        )->assertUnprocessable()
            ->assertJsonPath('message', 'The page draft request is invalid.')
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath('errors.lockVersion.0', 'The lock version field is required.');

        $this->assertSame($original, $page->fresh()->draftDocument->document);
    }

    public function test_empty_object_field_persists_and_returns_as_a_json_object_token(): void
    {
        $original = $this->fixture('empty-object-page');
        $page = $this->canonicalPage($original);
        $changed = $original;
        $changed['sections'][0]['customColors'] = new stdClass;
        $changed['sections'][1]['customColors'] = (object) [
            'titleColor' => '#ffffff',
            'eyebrowColor' => '#000000',
        ];
        $raw = json_encode(['lockVersion' => 1, 'document' => $changed], JSON_THROW_ON_ERROR);

        $response = $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            $raw,
        )->assertOk();

        $tokens = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(stdClass::class, $tokens->data->document->sections[0]->customColors);

        $stored = DB::table('page_documents')
            ->where('page_id', $page->id)
            ->where('stage', PageDocument::STAGE_DRAFT)
            ->value('document');
        $storedTokens = json_decode($stored, flags: JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(stdClass::class, $storedTokens->sections[0]->customColors);

        $readResponse = $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.draft.show', $page))
            ->assertOk();
        $readTokens = json_decode($readResponse->getContent(), flags: JSON_THROW_ON_ERROR);
        $storedHash = DB::table('page_documents')
            ->where('page_id', $page->id)
            ->where('stage', PageDocument::STAGE_DRAFT)
            ->value('content_hash');
        $persistedDocument = $page->fresh()->draftDocument->document;
        $hashableDocument = $this->hashableDocumentFromToken($readTokens->data->document);

        $this->assertSame($response->json('data.contentHash'), $readResponse->json('data.contentHash'));
        $this->assertInstanceOf(stdClass::class, $persistedDocument['sections'][0]['customColors']);
        $this->assertSame($storedHash, PageDocumentHasher::hash($persistedDocument));
        $this->assertSame($storedHash, PageDocumentHasher::hash($hashableDocument));

        $hashableDocument['sections'][1]->customColors = (object) array_reverse(
            get_object_vars($hashableDocument['sections'][1]->customColors),
            preserve_keys: true,
        );
        $this->assertSame($storedHash, PageDocumentHasher::hash($hashableDocument));
    }

    public function test_whole_number_float_has_a_stable_persisted_hash_and_numeric_response(): void
    {
        $original = $this->fixture('semantic-number-page');
        $page = $this->canonicalPage($original);
        $changed = $original;
        $changed['sections'][0]['overlayOpacity'] = 0.0;
        $raw = json_encode(
            ['lockVersion' => 1, 'document' => $changed],
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
        );

        $response = $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            $raw,
        )->assertOk();

        $tokens = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
        $persisted = $page->fresh()->draftDocument;

        $this->assertIsNumeric($tokens->data->document->sections[0]->overlayOpacity);
        $this->assertSame($persisted->content_hash, PageDocumentHasher::hash($persisted->document));
        $this->assertSame($persisted->content_hash, $response->json('data.contentHash'));
        $this->assertSame(
            PageDocumentHasher::hash(['value' => 0]),
            PageDocumentHasher::hash(['value' => 0.0]),
        );
    }

    public function test_hash_flags_remain_truthful_across_pre_and_post_persist_representations(): void
    {
        $document = $this->fixture('representation-equivalent-snapshots');
        $document['sections'][0]['customColors'] = new stdClass;
        $document['sections'][0]['overlayOpacity'] = 0.0;
        $page = app(PageCrudService::class)->create($document);
        $persistedDocument = $page->fresh()->draftDocument->document;

        $page->documents()->create([
            'stage' => PageDocument::STAGE_PUBLISHED,
            'slug' => $persistedDocument['slug'],
            'schema_version' => 1,
            'lock_version' => 1,
            'content_hash' => PageDocumentHasher::hash($persistedDocument),
            'document' => $persistedDocument,
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.index'))
            ->assertOk()
            ->assertJsonPath('data.0.hasPublishedSnapshot', true)
            ->assertJsonPath('data.0.hasUnpublishedChanges', false);
    }

    public function test_declared_arrays_remain_json_array_tokens_after_raw_round_trip(): void
    {
        $original = $this->fixture('array-token-page');
        $page = $this->canonicalPage($original);
        $changed = $original;
        $changed['sections'][0]['items'] = [];
        $changed['sections'][1]['items'][0]['features'] = [];
        $raw = json_encode(['lockVersion' => 1, 'document' => $changed], JSON_THROW_ON_ERROR);

        $response = $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            $raw,
        )->assertOk();

        $tokens = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($tokens->data->document->sections[0]->items);
        $this->assertIsArray($tokens->data->document->sections[1]->items[0]->features);
    }

    public function test_json_array_is_rejected_at_an_object_declared_field_path(): void
    {
        $original = $this->fixture('wrong-object-token-page');
        $page = $this->canonicalPage($original);
        $invalid = $original;
        $invalid['sections'][0]['customColors'] = [];
        $raw = json_encode(['lockVersion' => 1, 'document' => $invalid], JSON_THROW_ON_ERROR);

        $response = $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            $raw,
        )->assertUnprocessable()
            ->assertJsonPath('code', 'cms_validation_failed');

        $this->assertSame(
            ['This value must be a JSON object.'],
            $response->json('errors')['sections.0.customColors'],
        );
        $this->assertSame($original, $page->fresh()->draftDocument->document);
    }

    public function test_invalid_raw_json_returns_a_stable_422_without_mutation(): void
    {
        $original = $this->fixture('invalid-json-page');
        $page = $this->canonicalPage($original);

        $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            '{"lockVersion":1,"document":',
        )->assertUnprocessable()
            ->assertJsonPath('message', 'The page draft request is invalid.')
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath('errors.request.0', 'The request body must contain valid JSON.');

        $this->assertSame(1, $page->fresh()->draftDocument->lock_version);
    }

    public function test_non_object_raw_draft_envelope_returns_a_stable_422_without_mutation(): void
    {
        $original = $this->fixture('non-object-envelope-page');
        $page = $this->canonicalPage($original);

        $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            '[]',
        )->assertUnprocessable()
            ->assertJsonPath('message', 'The page draft request is invalid.')
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath('errors.request.0', 'The request body must contain a JSON object.');

        $this->assertSame(1, $page->fresh()->draftDocument->lock_version);
    }

    public function test_unexpected_raw_draft_envelope_key_returns_a_stable_422_without_mutation(): void
    {
        $original = $this->fixture('unexpected-envelope-page');
        $page = $this->canonicalPage($original);
        $raw = json_encode([
            'lockVersion' => 1,
            'document' => $original,
            'replacementDocument' => $original,
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($this->admin())->rawJson(
            'PATCH',
            route('admin.api.pages.draft.update', $page),
            $raw,
        )->assertUnprocessable()
            ->assertJsonPath('message', 'The page draft request is invalid.')
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath(
                'errors.replacementDocument.0',
                'This field is not supported by the page draft request.',
            );

        $this->assertSame(1, $page->fresh()->draftDocument->lock_version);
    }

    public function test_draft_save_rejects_an_unsafe_slug_without_mutation(): void
    {
        $original = $this->fixture('safe-page');
        $page = $this->canonicalPage($original);
        $invalid = $original;
        $invalid['slug'] = 'Unsafe Slug!';

        $this->actingAs($this->admin())->patchJson(
            route('admin.api.pages.draft.update', $page),
            ['lockVersion' => 1, 'document' => $invalid],
        )->assertUnprocessable()
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath(
                'errors.slug.0',
                'The slug must use lowercase letters, numbers, and single hyphens only.',
            );

        $draft = $page->fresh()->draftDocument;
        $this->assertSame($original, $draft->document);
        $this->assertSame(1, $draft->lock_version);
    }

    public function test_draft_save_rejects_a_slug_used_by_another_draft_without_mutation(): void
    {
        $original = $this->fixture('first-page');
        $page = $this->canonicalPage($original);
        $this->canonicalPage($this->fixture('already-used'));
        $invalid = $original;
        $invalid['slug'] = 'already-used';

        $this->actingAs($this->admin())->patchJson(
            route('admin.api.pages.draft.update', $page),
            ['lockVersion' => 1, 'document' => $invalid],
        )->assertUnprocessable()
            ->assertJsonPath('code', 'cms_validation_failed')
            ->assertJsonPath(
                'errors.slug.0',
                'This slug is already used by another draft page.',
            );

        $draft = $page->fresh()->draftDocument;
        $this->assertSame($original, $draft->document);
        $this->assertSame(1, $draft->lock_version);
    }

    public function test_unauthenticated_user_is_denied_all_task_four_endpoints_without_mutation(): void
    {
        $document = $this->fixture('unauthenticated-page');
        $page = $this->canonicalPage($document);

        foreach ($this->taskFourEndpointRequests($page, $document) as [$method, $url, $payload]) {
            $this->json($method, $url, $payload)->assertUnauthorized();
        }

        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseCount('page_documents', 1);
        $this->assertSame(1, $page->fresh()->draftDocument->lock_version);
        $this->assertSame($document, $page->fresh()->draftDocument->document);
    }

    public function test_authenticated_non_admin_is_denied_all_task_four_endpoints_without_mutation(): void
    {
        $document = $this->fixture('forbidden-page');
        $page = $this->canonicalPage($document);
        $user = User::factory()->create(['is_admin' => false]);

        foreach ($this->taskFourEndpointRequests($page, $document) as [$method, $url, $payload]) {
            $this->actingAs($user)->json($method, $url, $payload)->assertUnauthorized();
        }

        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseCount('page_documents', 1);
        $this->assertSame(1, $page->fresh()->draftDocument->lock_version);
        $this->assertSame($document, $page->fresh()->draftDocument->document);
    }

    public function test_all_task_four_routes_keep_the_web_auth_and_admin_middleware_boundary(): void
    {
        foreach ($this->taskFourRouteNames() as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('admin', $route->gatherMiddleware());
        }
    }

    public function test_create_writes_one_draft_document_transactionally_with_a_safe_unique_slug(): void
    {
        $first = $this->fixture('Summer Campaign!');
        $second = $first;
        $second['title'] = 'Second campaign';

        $firstResponse = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.store'), $first)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'summer-campaign')
            ->assertJsonPath('data.lockVersion', 1)
            ->assertJsonPath('data.hasPublishedSnapshot', false)
            ->assertJsonPath('data.hasUnpublishedChanges', true);

        $secondResponse = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.store'), $second)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'summer-campaign-2');

        $firstPage = Page::query()->findOrFail($firstResponse->json('data.id'));
        $secondPage = Page::query()->findOrFail($secondResponse->json('data.id'));

        $this->assertNull($firstPage->slug);
        $this->assertNull($firstPage->title);
        $this->assertSame('summer-campaign', $firstPage->draftDocument->document['slug']);
        $this->assertSame('summer-campaign-2', $secondPage->draftDocument->document['slug']);
        $this->assertDatabaseCount('pages', 2);
        $this->assertDatabaseCount('page_documents', 2);
        $this->assertDatabaseCount('page_sections', 0);
    }

    public function test_invalid_create_rolls_back_page_and_document(): void
    {
        $invalid = $this->fixture('invalid-create');
        $invalid['unexpectedRootField'] = true;

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.store'), $invalid)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'unsupported_contract_field')
            ->assertJsonPath(
                'errors.unexpectedRootField.0',
                'This field is not supported by the page contract.',
            );

        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('page_documents', 0);
    }

    public function test_duplicate_is_always_draft_with_unique_slug_and_fresh_noncolliding_section_ids(): void
    {
        $draft = $this->fixture('published-source');
        $page = $this->canonicalPage($draft, status: 'published', withPublishedSnapshot: true);

        $firstResponse = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.duplicate', $page))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'published-source-copy')
            ->assertJsonPath('data.hasPublishedSnapshot', false)
            ->assertJsonPath('data.hasUnpublishedChanges', true);

        $secondResponse = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.duplicate', $page))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'published-source-copy-2');

        $sourceIds = collect($draft['sections'])->pluck('id')->all();
        $firstIds = $firstResponse->json('data.sections.*.id');
        $secondIds = $secondResponse->json('data.sections.*.id');

        $this->assertCount(count($sourceIds), $firstIds);
        $this->assertSame([], array_values(array_intersect($sourceIds, $firstIds)));
        $this->assertSame([], array_values(array_intersect($sourceIds, $secondIds)));
        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));
        $this->assertSame($firstIds, $firstResponse->json('data.sections.*.id'));

        $firstCopy = Page::query()->findOrFail($firstResponse->json('data.id'));
        $secondCopy = Page::query()->findOrFail($secondResponse->json('data.id'));

        $this->assertSame('draft', $firstCopy->status);
        $this->assertSame('draft', $secondCopy->status);
        $this->assertNull($firstCopy->publishedDocument);
        $this->assertNull($secondCopy->publishedDocument);
        $this->assertSame(1, $firstCopy->documents()->count());
        $this->assertSame(1, $secondCopy->documents()->count());
    }

    public function test_duplicate_resolves_a_deterministic_section_id_collision_without_randomness(): void
    {
        $firstCandidate = '8665a966-265c-5f9a-9a1b-b419393ca277';
        $expectedResolvedId = '6be12a77-93a9-5f57-86c8-c2d9f676e2eb';
        $document = $this->fixture('collision-source');
        $document['sections'][0]['id'] = $firstCandidate;
        $page = $this->canonicalPage($document);

        $this->assertSame(1, $page->id);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.api.pages.duplicate', $page))
            ->assertCreated();
        $copyId = $response->json('data.id');
        $copySectionIds = $response->json('data.sections.*.id');

        $this->assertSame($expectedResolvedId, $copySectionIds[0]);
        $this->assertNotContains($firstCandidate, $copySectionIds);
        $this->assertCount(count($copySectionIds), array_unique($copySectionIds));

        $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.draft.show', $copyId))
            ->assertOk()
            ->assertJsonPath('data.document.sections.0.id', $expectedResolvedId);
    }

    public function test_list_derives_unpublished_change_flags_from_canonical_hashes(): void
    {
        $withoutSnapshot = $this->canonicalPage($this->fixture('no-snapshot'));
        $matchingSnapshot = $this->canonicalPage(
            $this->fixture('matching-snapshot'),
            status: 'published',
            withPublishedSnapshot: true,
        );
        $changedDraft = $this->fixture('changed-snapshot');
        $differentPublished = $changedDraft;
        $differentPublished['title'] = 'Published title';
        $changedPage = $this->canonicalPage(
            $changedDraft,
            status: 'published',
            publishedDocument: $differentPublished,
        );

        $data = $this->actingAs($this->admin())
            ->getJson(route('admin.api.pages.index'))
            ->assertOk()
            ->json('data');
        $byId = collect($data)->keyBy('id');

        $this->assertFalse($byId[(string) $withoutSnapshot->id]['hasPublishedSnapshot']);
        $this->assertTrue($byId[(string) $withoutSnapshot->id]['hasUnpublishedChanges']);
        $this->assertTrue($byId[(string) $matchingSnapshot->id]['hasPublishedSnapshot']);
        $this->assertFalse($byId[(string) $matchingSnapshot->id]['hasUnpublishedChanges']);
        $this->assertTrue($byId[(string) $changedPage->id]['hasPublishedSnapshot']);
        $this->assertTrue($byId[(string) $changedPage->id]['hasUnpublishedChanges']);
    }

    public function test_delete_cascades_documents_but_never_deletes_a_shared_media_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms/shared-hero.jpg', 'shared image');

        $document = $this->fixture('delete-page');
        $document['heroImage'] = '/storage/cms/shared-hero.jpg';
        $document['sections'][0]['mediaUrl'] = '/storage/cms/shared-hero.jpg';
        $page = $this->canonicalPage($document, status: 'published', withPublishedSnapshot: true);

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.api.pages.destroy', $page))
            ->assertNoContent();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('page_documents', ['page_id' => $page->id]);
        Storage::disk('public')->assertExists('cms/shared-hero.jpg');
    }

    public function test_delete_rolls_back_when_a_deletion_side_effect_fails(): void
    {
        $this->withoutExceptionHandling();

        $document = $this->fixture('delete-rollback-page');
        $page = $this->canonicalPage($document, status: 'published', withPublishedSnapshot: true);

        Page::deleted(function (): never {
            throw new RuntimeException('Simulated downstream delete failure.');
        });

        try {
            $this->actingAs($this->admin())
                ->deleteJson(route('admin.api.pages.destroy', $page));
            $this->fail('The simulated delete failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated downstream delete failure.', $exception->getMessage());
        }

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseCount('page_documents', 2);
    }

    private function rawJson(string $method, string $url, string $json): TestResponse
    {
        return $this->call($method, $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $json);
    }

    /** @return array<string, mixed> */
    private function hashableDocumentFromToken(stdClass $document): array
    {
        return array_map($this->hashableTokenValue(...), get_object_vars($document));
    }

    private function hashableTokenValue(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);

            foreach ($properties as $field => $fieldValue) {
                $properties[$field] = $this->hashableTokenValue($fieldValue);
            }

            return (object) $properties;
        }

        if (is_array($value)) {
            return array_map($this->hashableTokenValue(...), $value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function taskFourEndpointRequests(Page $page, array $document): array
    {
        $draftWrite = ['lockVersion' => 1, 'document' => $document];

        return [
            ['GET', route('admin.api.pages.index'), []],
            ['POST', route('admin.api.pages.store'), $document],
            ['GET', route('admin.api.pages.draft.show', $page), []],
            ['PATCH', route('admin.api.pages.draft.update', $page), $draftWrite],
            ['PATCH', route('admin.api.pages.update', $page), $draftWrite],
            ['POST', route('admin.api.pages.duplicate', $page), []],
            ['DELETE', route('admin.api.pages.destroy', $page), []],
        ];
    }

    /** @return list<string> */
    private function taskFourRouteNames(): array
    {
        return [
            'admin.api.pages.index',
            'admin.api.pages.store',
            'admin.api.pages.draft.show',
            'admin.api.pages.draft.update',
            'admin.api.pages.update',
            'admin.api.pages.duplicate',
            'admin.api.pages.destroy',
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @return array<string, mixed> */
    private function fixture(string $slug): array
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

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>|null  $publishedDocument
     */
    private function canonicalPage(
        array $document,
        string $status = 'draft',
        int $lockVersion = 1,
        bool $withPublishedSnapshot = false,
        ?array $publishedDocument = null,
        bool $legacyIdentity = false,
    ): Page {
        $page = Page::query()->create([
            'status' => $status,
            'slug' => $legacyIdentity ? 'legacy-page-slug' : null,
            'title' => $legacyIdentity ? 'Legacy page title' : null,
            'metadata' => $legacyIdentity ? ['description' => 'Legacy metadata'] : null,
            'is_published' => $legacyIdentity,
        ]);

        $page->documents()->create([
            'stage' => PageDocument::STAGE_DRAFT,
            'slug' => $document['slug'],
            'schema_version' => 1,
            'lock_version' => $lockVersion,
            'content_hash' => PageDocumentHasher::hash($document),
            'document' => $document,
        ]);

        if ($withPublishedSnapshot || $publishedDocument !== null) {
            $publishedDocument ??= $document;
            $page->documents()->create([
                'stage' => PageDocument::STAGE_PUBLISHED,
                'slug' => $publishedDocument['slug'],
                'schema_version' => 1,
                'lock_version' => 1,
                'content_hash' => PageDocumentHasher::hash($publishedDocument),
                'document' => $publishedDocument,
            ]);
        }

        return $page->fresh();
    }
}
