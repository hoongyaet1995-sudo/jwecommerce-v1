<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Domain\Cms\PageDraftService;
use App\Domain\Cms\PagePublicationService;
use App\Models\CmsMediaAsset;
use App\Models\CmsMediaReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsMediaAssetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_uploads_a_server_verified_raster_to_a_random_managed_path(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.api.media-assets.store'), [
                'file' => $this->sourceJpeg('customer-controlled-name.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.mimeType', 'image/jpeg');

        $path = $response->json('data.path');
        $url = $response->json('data.url');

        $this->assertIsString($path);
        $this->assertMatchesRegularExpression(
            '#\Acms/media/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.jpg\z#',
            $path,
        );
        $this->assertSame('/storage/'.$path, $url);
        $this->assertStringNotContainsString('customer-controlled-name', $path);
        Storage::disk('public')->assertExists($path);

        $asset = CmsMediaAsset::query()->findOrFail($response->json('data.id'));
        $this->assertSame('public', $asset->disk);
        $this->assertSame($path, $asset->path);
        $this->assertSame($url, $asset->url);
        $this->assertSame('customer-controlled-name.jpg', $asset->original_name);
        $this->assertSame(1024, $asset->width);
        $this->assertSame(1024, $asset->height);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $asset->checksum);
        $this->assertNotNull($asset->orphaned_at);
        $this->assertDatabaseCount('cms_media_references', 0);
    }

    public function test_upload_policy_rejects_unsafe_mime_extension_size_and_dimensions_with_stable_errors(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $cases = [
            'svg payload' => UploadedFile::fake()->createWithContent(
                'vector.jpg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ),
            'executable extension with image bytes' => $this->sourceJpeg('shell.php'),
            'jpeg with executable trailing payload' => UploadedFile::fake()->createWithContent(
                'polyglot.jpg',
                (file_get_contents(base_path('public/images/source/jwstudio_logo_1786766390670.jpg')) ?: '')
                    .'<?php echo "unsafe"; ?>',
            ),
            'image over ten MiB' => UploadedFile::fake()->create('large.png', 10 * 1024 + 1, 'image/png'),
            'image wider than the dimension ceiling' => UploadedFile::fake()->createWithContent(
                'wide.png',
                $this->png(8001, 1),
            ),
            'video over thirty MiB' => UploadedFile::fake()->create('large.mp4', 30 * 1024 + 1, 'video/mp4'),
        ];

        foreach ($cases as $label => $file) {
            $this->actingAs($admin)
                ->post(route('admin.api.media-assets.store'), ['file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'The media asset upload is invalid.')
                ->assertJsonPath('code', 'cms_media_upload_invalid')
                ->assertJsonStructure(['errors' => ['file']]);

            $this->assertSame(0, CmsMediaAsset::query()->count(), $label);
        }

        $this->assertSame([], Storage::disk('public')->allFiles('cms/media'));
    }

    public function test_dedicated_video_policy_accepts_only_server_detected_mp4_and_webm_containers(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $cases = [
            'clip.mp4' => $this->minimalMp4(),
            'clip.webm' => $this->minimalWebm(),
        ];

        foreach ($cases as $name => $bytes) {
            $response = $this->actingAs($admin)
                ->post(route('admin.api.media-assets.store'), [
                    'file' => UploadedFile::fake()->createWithContent($name, $bytes ?: ''),
                ], ['Accept' => 'application/json'])
                ->assertCreated();

            $asset = CmsMediaAsset::query()->findOrFail($response->json('data.id'));
            $this->assertContains($asset->mime_type, ['video/mp4', 'video/webm']);
            $this->assertNull($asset->width);
            $this->assertNull($asset->height);
            Storage::disk('public')->assertExists($asset->path);
        }

        $this->assertSame(['video/mp4', 'video/webm'], CmsMediaAsset::query()->orderBy('mime_type')->pluck('mime_type')->all());
    }

    public function test_video_policy_rejects_header_only_missing_structure_and_trailing_payloads(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $mp4Header = hex2bin('00000018667479706d703432000000006d70343269736f6d');
        $webmHeader = hex2bin('1a45dfa39f4286810142f7810142f2810442f381084282847765626d4287810442858102');
        $cases = [
            'header-only.mp4' => is_string($mp4Header) ? $mp4Header : '',
            'missing-trak.mp4' => $this->mp4Box('ftyp', 'isom'.pack('N', 0).'isommp42')
                .$this->mp4Box('moov', $this->mp4Box('mvhd', "\0\0\0\0"))
                .$this->mp4Box('mdat', "\0"),
            'appended.mp4' => $this->minimalMp4().'UNPARSED',
            'header-only.webm' => is_string($webmHeader) ? $webmHeader : '',
            'missing-tracks.webm' => $this->minimalWebm(includeTracks: false),
            'appended.webm' => $this->minimalWebm()."\0UNPARSED",
        ];

        foreach ($cases as $label => $bytes) {
            $this->actingAs($admin)
                ->post(route('admin.api.media-assets.store'), [
                    'file' => UploadedFile::fake()->createWithContent($label, $bytes),
                ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'cms_media_upload_invalid')
                ->assertJsonPath('errors.file.0', 'The video content does not match its detected type.');
        }

        $this->assertDatabaseCount('cms_media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('cms/media'));
    }

    public function test_raster_policy_accepts_server_detected_png_gif_and_webp(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $cases = [
            'pixel.png' => $this->png(1, 1),
            'pixel.gif' => base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true),
            'pixel.webp' => base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA', true),
        ];

        foreach ($cases as $name => $bytes) {
            $response = $this->actingAs($admin)
                ->post(route('admin.api.media-assets.store'), [
                    'file' => UploadedFile::fake()->createWithContent($name, is_string($bytes) ? $bytes : ''),
                ], ['Accept' => 'application/json'])
                ->assertCreated();

            $asset = CmsMediaAsset::query()->findOrFail($response->json('data.id'));
            $this->assertSame(1, $asset->width);
            $this->assertSame(1, $asset->height);
            Storage::disk('public')->assertExists($asset->path);
        }

        $this->assertEqualsCanonicalizing(
            ['image/png', 'image/gif', 'image/webp'],
            CmsMediaAsset::query()->pluck('mime_type')->all(),
        );
    }

    public function test_upload_endpoint_is_protected_and_keeps_web_csrf_middleware(): void
    {
        Storage::fake('public');
        $url = route('admin.api.media-assets.store');

        $this->post($url, [
            'file' => $this->sourceJpeg('hero.jpg'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post($url, [
                'file' => $this->sourceJpeg('hero.jpg'),
            ], ['Accept' => 'application/json'])->assertUnauthorized();

        $route = Route::getRoutes()->getByName('admin.api.media-assets.store');
        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertDatabaseCount('cms_media_assets', 0);
    }

    public function test_document_contract_rejects_base64_in_every_media_field_family_with_exact_paths(): void
    {
        $contract = app(PageDocumentContract::class);
        $value = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB';
        $cases = [
            'heroImage' => $this->document(heroImage: $value),
            'sections.0.mediaUrl' => $this->document(sectionType: 'hero_banner', sectionFields: ['mediaUrl' => $value]),
            'sections.0.authorAvatar' => $this->document(sectionType: 'rich_text', sectionFields: ['authorAvatar' => $value]),
            'sections.0.videoEmbedUrl' => $this->document(sectionType: 'video_embed', sectionFields: ['videoEmbedUrl' => $value]),
            'sections.0.beforeImageUrl' => $this->document(sectionType: 'before_after', sectionFields: ['beforeImageUrl' => $value]),
            'sections.0.afterImageUrl' => $this->document(sectionType: 'before_after', sectionFields: ['afterImageUrl' => $value]),
            'sections.0.items.0.imageUrl' => $this->itemDocument(['imageUrl' => $value]),
            'sections.0.items.0.videoUrl' => $this->itemDocument(['videoUrl' => $value]),
            'sections.0.items.0.authorAvatar' => $this->itemDocument(['authorAvatar' => $value]),
        ];

        foreach ($cases as $path => $document) {
            try {
                $contract->validate($document);
                $this->fail("Expected unsafe media at [{$path}] to be rejected.");
            } catch (ValidationException $exception) {
                $this->assertSame(
                    ['Data URLs are not allowed in page documents.'],
                    $exception->errors()[$path] ?? null,
                );
            }
        }
    }

    public function test_document_contract_allows_only_managed_relative_or_safe_public_https_media_urls(): void
    {
        $contract = app(PageDocumentContract::class);
        $managed = '/storage/cms/media/7ea92b56-24d3-4f35-b80c-44cf0bd78292.webp';
        $external = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1200&q=80';
        $encodedPath = 'https://cdn.example.org/product%20hero.jpg';

        $this->assertSame($managed, $contract->validate($this->document(heroImage: $managed))['heroImage']);
        $this->assertSame($external, $contract->validate($this->document(heroImage: $external))['heroImage']);
        $this->assertSame($encodedPath, $contract->validate($this->document(heroImage: $encodedPath))['heroImage']);
        $this->assertSame('', $contract->validate($this->document(heroImage: ''))['heroImage']);

        $unsafe = [
            'javascript:alert(1)',
            '//evil.example/media.jpg',
            'http://cdn.example.test/media.jpg',
            'https://user:password@public.example.test/media.jpg',
            'https://localhost/media.jpg',
            'https://127.0.0.1/media.jpg',
            'https://192.168.1.5/media.jpg',
            'https://198.51.100.10/media.jpg',
            'https://[::1]/media.jpg',
            'https://[::ffff:127.0.0.1]/media.jpg',
            'https://[::ffff:7f00:1]/media.jpg',
            'https://127.1/media.jpg',
            'https://0177.0.0.1/media.jpg',
            'https://0x7f000001/media.jpg',
            'https://2130706433/media.jpg',
            'https://metadata.google.internal/media.jpg',
            'https://cdn.example.org/%5c/private.jpg',
            'https://cdn.example.org/%255c/private.jpg',
            'https://cdn.example.org/%00private.jpg',
            'https://cdn.example.org/%250d%250aHost:evil.example/private.jpg',
            'https://cdn.example.org/%09private.jpg',
            '/storage/customer-controlled/file.jpg',
        ];

        foreach ($unsafe as $url) {
            try {
                $contract->validate($this->document(heroImage: $url));
                $this->fail("Expected unsafe media URL [{$url}] to be rejected.");
            } catch (ValidationException $exception) {
                $this->assertSame(
                    ['Media must use a managed asset URL or a safe public HTTPS URL.'],
                    $exception->errors()['heroImage'] ?? null,
                );
            }
        }
    }

    public function test_reference_lifecycle_protects_shared_draft_published_and_duplicate_assets_without_deleting_files(): void
    {
        Storage::fake('public');
        $asset = $this->asset();
        Storage::disk('public')->put($asset->path, 'managed-file');

        $source = app(PageCrudService::class)->create($this->document(heroImage: $asset->url));
        $asset->refresh();
        $this->assertNull($asset->orphaned_at);
        $this->assertDatabaseHas('cms_media_references', [
            'asset_id' => $asset->getKey(),
            'page_document_id' => $source->draftDocument->getKey(),
            'field_path' => 'heroImage',
        ]);

        $published = app(PagePublicationService::class)->publish($source, 1);
        $copy = app(PageCrudService::class)->duplicate($published);
        $this->assertSame(3, CmsMediaReference::query()->where('asset_id', $asset->getKey())->count());

        app(PageDraftService::class)->save($source, $this->document(slug: $source->draftDocument->slug), 1);
        app(PageCrudService::class)->delete($copy);
        $this->assertNull($asset->fresh()->orphaned_at);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));

        app(PageCrudService::class)->delete($source);
        $this->assertNotNull($asset->fresh()->orphaned_at);
        $this->assertDatabaseMissing('cms_media_references', ['asset_id' => $asset->getKey()]);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
    }

    public function test_canonical_delete_api_uses_shared_and_last_reference_orphan_semantics(): void
    {
        Storage::fake('public');
        $asset = $this->asset();
        Storage::disk('public')->put($asset->path, 'managed-file');
        $first = app(PageCrudService::class)->create($this->document(slug: 'legacy-delete-first', heroImage: $asset->url));
        $second = app(PageCrudService::class)->create($this->document(slug: 'legacy-delete-second', heroImage: $asset->url));
        $admin = $this->admin();

        $this->actingAs($admin)
            ->deleteJson(route('admin.api.pages.destroy', $first))
            ->assertNoContent();

        $this->assertNull($asset->fresh()->orphaned_at);
        $this->assertSame(1, CmsMediaReference::query()->where('asset_id', $asset->getKey())->count());
        $this->assertTrue(Storage::disk('public')->exists($asset->path));

        $this->actingAs($admin)
            ->deleteJson(route('admin.api.pages.destroy', $second))
            ->assertNoContent();

        $this->assertNotNull($asset->fresh()->orphaned_at);
        $this->assertDatabaseMissing('cms_media_references', ['asset_id' => $asset->getKey()]);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
    }

    public function test_reference_synchronizer_acquires_all_asset_rows_in_sorted_global_order(): void
    {
        $lower = $this->asset(id: '10000000-0000-4000-8000-000000000001');
        $higher = $this->asset(id: 'f0000000-0000-4000-8000-000000000001');
        $assetQueries = [];
        DB::listen(static function ($query) use (&$assetQueries): void {
            if (str_contains(strtolower($query->sql), 'cms_media_assets')) {
                $assetQueries[] = ['sql' => strtolower($query->sql), 'bindings' => $query->bindings];
            }
        });

        app(PageCrudService::class)->create($this->document(
            heroImage: $higher->url,
            sectionFields: ['mediaUrl' => $lower->url],
        ));

        $lockPlan = collect($assetQueries)->first(static fn (array $query): bool => str_contains($query['sql'], 'where')
            && str_contains($query['sql'], '"cms_media_assets"."id" in')
        );

        $this->assertNotNull($lockPlan, 'Expected one globally ordered asset lock query.');
        $this->assertStringContainsString('order by "id" asc', $lockPlan['sql']);
        $this->assertSame([$lower->getKey(), $higher->getKey()], array_values($lockPlan['bindings']));
    }

    public function test_orphan_cleanup_scope_requires_thirty_days_and_no_references(): void
    {
        $recent = $this->asset(orphanedAt: now()->subDays(29));
        $old = $this->asset(orphanedAt: now()->subDays(30));
        $referencedOld = $this->asset(orphanedAt: now()->subDays(60));
        $page = app(PageCrudService::class)->create($this->document(heroImage: $referencedOld->url));

        $this->assertNull($referencedOld->fresh()->orphaned_at);
        $this->assertEqualsCanonicalizing(
            [$old->getKey()],
            CmsMediaAsset::query()->cleanupEligible()->pluck('id')->all(),
        );
        $this->assertNotContains($recent->getKey(), CmsMediaAsset::query()->cleanupEligible()->pluck('id')->all());

        app(PageCrudService::class)->delete($page);
        $referencedOld->refresh()->forceFill(['orphaned_at' => now()->subDays(30)])->save();
        $this->assertEqualsCanonicalizing(
            [$old->getKey(), $referencedOld->getKey()],
            CmsMediaAsset::query()->cleanupEligible()->pluck('id')->all(),
        );
    }

    public function test_external_urls_never_create_managed_reference_rows(): void
    {
        $page = app(PageCrudService::class)->create($this->document(
            heroImage: 'https://images.unsplash.com/photo-safe?auto=format&fit=crop&w=1200',
        ));

        $this->assertNotNull($page->draftDocument);
        $this->assertDatabaseCount('cms_media_assets', 0);
        $this->assertDatabaseCount('cms_media_references', 0);
    }

    public function test_synchronizer_indexes_every_media_field_family_at_its_exact_path(): void
    {
        $cases = [
            'heroImage' => fn (string $url): array => $this->document(heroImage: $url),
            'sections.0.mediaUrl' => fn (string $url): array => $this->document(sectionFields: ['mediaUrl' => $url]),
            'sections.0.authorAvatar' => fn (string $url): array => $this->document(sectionType: 'rich_text', sectionFields: ['authorAvatar' => $url]),
            'sections.0.videoEmbedUrl' => fn (string $url): array => $this->document(sectionType: 'video_embed', sectionFields: ['videoEmbedUrl' => $url]),
            'sections.0.beforeImageUrl' => fn (string $url): array => $this->document(sectionType: 'before_after', sectionFields: ['beforeImageUrl' => $url]),
            'sections.0.afterImageUrl' => fn (string $url): array => $this->document(sectionType: 'before_after', sectionFields: ['afterImageUrl' => $url]),
            'sections.0.items.0.imageUrl' => fn (string $url): array => $this->itemDocument(['imageUrl' => $url]),
            'sections.0.items.0.videoUrl' => fn (string $url): array => $this->itemDocument(['videoUrl' => $url]),
            'sections.0.items.0.authorAvatar' => fn (string $url): array => $this->itemDocument(['authorAvatar' => $url]),
        ];

        $offset = 0;

        foreach ($cases as $path => $factory) {
            $asset = $this->asset();
            $document = $factory($asset->url);
            $document['slug'] = 'media-path-'.$offset;
            $page = app(PageCrudService::class)->create($document);

            $this->assertDatabaseHas('cms_media_references', [
                'asset_id' => $asset->getKey(),
                'page_document_id' => $page->draftDocument->getKey(),
                'field_path' => $path,
            ]);
            $this->assertNull($asset->fresh()->orphaned_at);
            $offset++;
        }
    }

    public function test_missing_managed_asset_rolls_back_create_and_draft_cas_write(): void
    {
        $missingUrl = '/storage/cms/media/0f45f148-176b-45cb-976c-1ecf2ec2ca25.jpg';

        try {
            app(PageCrudService::class)->create($this->document(heroImage: $missingUrl));
            $this->fail('Expected missing managed asset creation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(['The managed media asset does not exist.'], $exception->errors()['heroImage']);
        }
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('page_documents', 0);

        $page = app(PageCrudService::class)->create($this->document(slug: 'existing-safe'));
        $before = $page->draftDocument->only(['document', 'content_hash', 'lock_version']);

        try {
            app(PageDraftService::class)->save(
                $page,
                $this->document(slug: 'existing-safe', heroImage: $missingUrl),
                1,
            );
            $this->fail('Expected missing managed asset save to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(['The managed media asset does not exist.'], $exception->errors()['heroImage']);
        }

        $after = $page->fresh()->draftDocument;
        $this->assertSame($before['document'], $after->document);
        $this->assertSame($before['content_hash'], $after->content_hash);
        $this->assertSame($before['lock_version'], $after->lock_version);
    }

    public function test_reference_sync_failure_rolls_back_publication_snapshot_and_status(): void
    {
        $page = app(PageCrudService::class)->create($this->document(slug: 'publication-rollback'));
        $missingUrl = '/storage/cms/media/76cd68dc-ecf8-4604-9758-a71c11391919.jpg';
        $invalidDocument = $this->document(slug: 'publication-rollback', heroImage: $missingUrl);

        DB::table('page_documents')
            ->where('id', $page->draftDocument->getKey())
            ->update([
                'document' => json_encode($invalidDocument, JSON_THROW_ON_ERROR),
                'content_hash' => PageDocumentHasher::hash($invalidDocument),
            ]);

        try {
            app(PagePublicationService::class)->publish($page->fresh(), 1);
            $this->fail('Expected missing managed asset publication to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(['The managed media asset does not exist.'], $exception->errors()['heroImage']);
        }

        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->publishedDocument);
        $this->assertDatabaseCount('page_documents', 1);
    }

    public function test_reference_sync_failure_rolls_back_duplicate_page_and_document_creation(): void
    {
        $source = app(PageCrudService::class)->create($this->document(slug: 'duplicate-rollback'));
        $missingUrl = '/storage/cms/media/b0a49179-8f30-4e80-86c8-690ba780a9e8.jpg';
        $invalidDocument = $this->document(slug: 'duplicate-rollback', heroImage: $missingUrl);

        DB::table('page_documents')
            ->where('id', $source->draftDocument->getKey())
            ->update([
                'document' => json_encode($invalidDocument, JSON_THROW_ON_ERROR),
                'content_hash' => PageDocumentHasher::hash($invalidDocument),
            ]);

        try {
            app(PageCrudService::class)->duplicate($source->fresh());
            $this->fail('Expected missing managed asset duplication to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(['The managed media asset does not exist.'], $exception->errors()['heroImage']);
        }

        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseCount('page_documents', 1);
        $this->assertDatabaseCount('cms_media_references', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function sourceJpeg(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path('public/images/source/jwstudio_logo_1786766390670.jpg'),
            $name,
            'image/jpeg',
            null,
            true,
        );
    }

    private function png(int $width, int $height): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $scanlines = str_repeat("\0".str_repeat("\0", $width * 3), $height);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($scanlines, 9))
            .$chunk('IEND', '');
    }

    private function asset(?\DateTimeInterface $orphanedAt = null, ?string $id = null): CmsMediaAsset
    {
        $id ??= (string) Str::uuid();
        $path = "cms/media/{$id}.jpg";

        return CmsMediaAsset::query()->create([
            'id' => $id,
            'disk' => 'public',
            'path' => $path,
            'url' => '/storage/'.$path,
            'original_name' => 'fixture.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 128,
            'width' => 1,
            'height' => 1,
            'checksum' => hash('sha256', $id),
            'uploader_id' => null,
            'orphaned_at' => $orphanedAt ?? now(),
        ]);
    }

    private function mp4Box(string $type, string $payload): string
    {
        return pack('N', 8 + strlen($payload)).$type.$payload;
    }

    private function minimalMp4(): string
    {
        return $this->mp4Box('ftyp', 'isom'.pack('N', 0).'isommp42')
            .$this->mp4Box('moov', $this->mp4Box('trak', $this->mp4Box('tkhd', "\0\0\0\0")))
            .$this->mp4Box('mdat', "\0\0\0\0");
    }

    private function webmElement(string $id, string $payload): string
    {
        $length = strlen($payload);
        $this->assertLessThan(127, $length);

        return $id.chr(0x80 | $length).$payload;
    }

    private function minimalWebm(bool $includeTracks = true): string
    {
        $header = hex2bin('1a45dfa39f4286810142f7810142f2810442f381084282847765626d4287810442858102');
        $trackEntry = $this->webmElement("\xae", $this->webmElement("\xd7", "\x01"));
        $tracks = $includeTracks ? $this->webmElement("\x16\x54\xae\x6b", $trackEntry) : '';
        $cluster = $this->webmElement("\x1f\x43\xb6\x75", $this->webmElement("\xa3", "\0"));

        return (is_string($header) ? $header : '')
            .$this->webmElement("\x18\x53\x80\x67", $tracks.$cluster);
    }

    /** @return array<string, mixed> */
    private function document(
        string $slug = 'managed-media-page',
        ?string $heroImage = null,
        string $sectionType = 'hero_banner',
        array $sectionFields = [],
    ): array {
        return [
            'title' => 'Managed Media Page',
            'slug' => $slug,
            'description' => 'Managed media contract fixture.',
            'template' => 'landing',
            'heroImage' => $heroImage,
            'seoTitle' => 'Managed Media Page',
            'seoDescription' => 'Managed media contract fixture.',
            'sections' => [array_merge([
                'id' => 'managed-media-section',
                'type' => $sectionType,
                'title' => 'Managed media',
                'isVisible' => false,
            ], $sectionFields)],
        ];
    }

    /** @return array<string, mixed> */
    private function itemDocument(array $itemFields): array
    {
        return $this->document(
            sectionType: 'media_gallery',
            sectionFields: ['items' => [array_merge([
                'id' => 'managed-media-item',
                'title' => 'Managed item',
            ], $itemFields)]],
        );
    }
}
