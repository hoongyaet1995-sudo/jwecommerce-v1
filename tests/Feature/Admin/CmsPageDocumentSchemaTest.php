<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageDocumentHasher;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsPageDocumentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_owns_one_draft_and_one_published_document(): void
    {
        $this->assertTrue(Schema::hasColumns('pages', ['status', 'published_at', 'archived_at']));
        $this->assertTrue(Schema::hasTable('page_documents'));

        $page = Page::query()->create(['status' => 'draft']);
        $page->documents()->create([
            'stage' => 'draft',
            'slug' => 'brand-story',
            'schema_version' => 1,
            'lock_version' => 1,
            'content_hash' => hash('sha256', '{}'),
            'document' => ['title' => 'Brand Story', 'slug' => 'brand-story', 'sections' => []],
        ]);

        $this->assertSame('brand-story', $page->fresh()->draftDocument->slug);
        $this->assertNull($page->fresh()->publishedDocument);
    }

    public function test_document_hash_is_stable_for_object_key_order_and_sensitive_to_section_order(): void
    {
        $first = [
            'title' => 'Brand Story',
            'sections' => [
                ['id' => 'sec-one', 'type' => 'hero_banner'],
                ['id' => 'sec-two', 'type' => 'rich_text'],
            ],
            'seo' => ['description' => 'Story', 'title' => 'Brand Story'],
        ];
        $sameObjectDifferentInsertionOrder = [
            'seo' => ['title' => 'Brand Story', 'description' => 'Story'],
            'sections' => [
                ['type' => 'hero_banner', 'id' => 'sec-one'],
                ['type' => 'rich_text', 'id' => 'sec-two'],
            ],
            'title' => 'Brand Story',
        ];
        $sameSectionsDifferentOrder = [
            'title' => 'Brand Story',
            'sections' => [
                ['id' => 'sec-two', 'type' => 'rich_text'],
                ['id' => 'sec-one', 'type' => 'hero_banner'],
            ],
            'seo' => ['description' => 'Story', 'title' => 'Brand Story'],
        ];

        $this->assertSame(
            PageDocumentHasher::hash($first),
            PageDocumentHasher::hash($sameObjectDifferentInsertionOrder),
        );
        $this->assertNotSame(
            PageDocumentHasher::hash($first),
            PageDocumentHasher::hash($sameSectionsDifferentOrder),
        );
    }
}
