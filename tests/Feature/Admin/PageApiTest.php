<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_publish_and_unpublish_a_canonical_page_without_public_route_cutover(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = $this->pagePayload();

        $created = $this->actingAs($admin)
            ->postJson(route('admin.api.pages.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'brand-story')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.hasPublishedSnapshot', false);

        $pageId = (string) $created->json('data.id');
        $page = Page::query()->findOrFail($pageId);

        $this->assertDatabaseHas('pages', [
            'id' => $pageId,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('page_documents', [
            'page_id' => $pageId,
            'stage' => PageDocument::STAGE_DRAFT,
            'slug' => 'brand-story',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.api.pages.publish', $page), [
                'lockVersion' => $created->json('data.lockVersion'),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.hasPublishedSnapshot', true)
            ->assertJsonPath('data.hasUnpublishedChanges', false);

        $runtime = app(PageRuntimePayloadFactory::class)->forPublished($page->fresh());
        $this->assertSame('A story stored in MySQL', $runtime['page']['document']['sections'][0]['title']);
        $this->assertSame(PageDocument::STAGE_PUBLISHED, $runtime['page']['stage']);

        $this->actingAs($admin)
            ->postJson(route('admin.api.pages.unpublish', $page))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.hasPublishedSnapshot', true);

        $this->expectException(NotFoundHttpException::class);
        app(PageRuntimePayloadFactory::class)->forPublished($page->fresh());
    }

    public function test_admin_cannot_publish_a_visible_section_without_a_renderer_capability(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = $this->pagePayload();
        $payload['sections'][0]['type'] = 'split_hero';

        $created = $this->actingAs($admin)
            ->postJson(route('admin.api.pages.store'), $payload)
            ->assertCreated();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.api.pages.publish', $created->json('data.id')), [
                'lockVersion' => $created->json('data.lockVersion'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cms_publication_blocked');

        $this->assertSame(
            ['This visible section type cannot be rendered.'],
            $response->json('errors')['sections.0.type'],
        );

        $this->assertDatabaseHas('pages', [
            'id' => $created->json('data.id'),
            'status' => 'draft',
        ]);
        $this->assertDatabaseMissing('page_documents', [
            'page_id' => $created->json('data.id'),
            'stage' => PageDocument::STAGE_PUBLISHED,
        ]);
    }

    public function test_cms_api_rejects_an_unauthenticated_write(): void
    {
        $this->postJson(route('admin.api.pages.store'), $this->pagePayload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('pages', 0);
    }

    public function test_canonical_draft_patch_reorders_sections_and_persists_the_fresh_document_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $document = $this->pagePayload();
        $document['sections'][] = [
            'id' => 'sec-second',
            'type' => 'hero_banner',
            'title' => 'Second section',
            'subtitle' => 'SECOND',
            'content' => 'Second content.',
            'buttonText' => '',
            'buttonLink' => '',
            'isVisible' => true,
        ];
        $document['sections'][] = [
            'id' => 'sec-third',
            'type' => 'hero_banner',
            'title' => 'Third section',
            'subtitle' => 'THIRD',
            'content' => 'Third content.',
            'buttonText' => '',
            'buttonLink' => '',
            'isVisible' => true,
        ];

        $created = $this->actingAs($admin)
            ->postJson(route('admin.api.pages.store'), $document)
            ->assertCreated();
        $page = Page::query()->findOrFail($created->json('data.id'));
        $before = $this->actingAs($admin)
            ->getJson(route('admin.api.pages.draft.show', $page))
            ->assertOk()
            ->json('data');
        $reordered = $document;
        $reordered['sections'] = [$document['sections'][2], $document['sections'][0], $document['sections'][1]];

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.api.pages.draft.update', $page), [
                'lockVersion' => $before['lockVersion'],
                'document' => $reordered,
            ])
            ->assertOk()
            ->assertJsonPath('data.lockVersion', $before['lockVersion'] + 1)
            ->assertJsonPath('data.document.sections.0.id', 'sec-third')
            ->assertJsonPath('data.document.sections.1.id', 'sec-new-hero')
            ->assertJsonPath('data.document.sections.2.id', 'sec-second');

        $after = $response->json('data');
        $this->assertNotSame($before['contentHash'], $after['contentHash']);
        $this->assertSame(['sec-third', 'sec-new-hero', 'sec-second'], array_column($page->fresh()->draftDocument->document['sections'], 'id'));
        $this->assertSame($after['contentHash'], $page->fresh()->draftDocument->content_hash);
        $this->assertSame(['sec-third', 'sec-new-hero', 'sec-second'], array_column(
            $this->actingAs($admin)
                ->getJson(route('admin.api.pages.draft.show', $page))
                ->assertOk()
                ->json('data.document.sections'),
            'id',
        ));
        $this->assertDatabaseCount('page_sections', 0);
    }

    /** @return array<string, mixed> */
    private function pagePayload(): array
    {
        return [
            'title' => 'Brand Story',
            'slug' => 'brand-story',
            'description' => 'A persisted page.',
            'template' => 'landing',
            'seoTitle' => 'Brand Story | JW Studio',
            'seoDescription' => 'A public CMS page.',
            'sections' => [[
                'id' => 'sec-new-hero',
                'type' => 'hero_banner',
                'title' => 'A story stored in MySQL',
                'subtitle' => 'BRAND STORY',
                'content' => 'This text must render after a refresh.',
                'buttonText' => 'Explore',
                'buttonLink' => '/shop',
                'isVisible' => true,
            ]],
        ];
    }
}
