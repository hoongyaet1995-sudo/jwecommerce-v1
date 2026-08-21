<?php

namespace Tests\Feature\Cms;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StorefrontPageController;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\Product;
use App\Models\User;
use App\Support\ViteManifestEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SharedPageRendererTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestDirectory;

    private string $manifestPath;

    private ?string $externalAssetDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestDirectory = storage_path('framework/testing/task-10-'.bin2hex(random_bytes(6)));
        $this->manifestPath = $this->manifestDirectory.'/manifest.json';
        File::ensureDirectoryExists($this->manifestDirectory.'/assets');
        File::put($this->manifestDirectory.'/assets/main.js', 'main');
        File::put($this->manifestDirectory.'/assets/public.js', 'public');
        File::put($this->manifestDirectory.'/assets/shared.js', 'shared');
        File::put($this->manifestDirectory.'/assets/main.css', 'main css');
        File::put($this->manifestDirectory.'/assets/public.css', 'public css');
        File::put($this->manifestDirectory.'/assets/shared.css', 'shared css');
        $this->writeManifest($this->safeManifest());

        $this->app->instance(
            ViteManifestEntry::class,
            new ViteManifestEntry($this->manifestPath, $this->manifestDirectory),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->manifestDirectory) && File::isDirectory($this->manifestDirectory)) {
            File::deleteDirectory($this->manifestDirectory);
        }

        if ($this->externalAssetDirectory && File::isDirectory($this->externalAssetDirectory)) {
            File::deleteDirectory($this->externalAssetDirectory);
        }

        parent::tearDown();
    }

    public function test_preview_and_canary_routes_are_protected_by_the_existing_admin_boundary(): void
    {
        $page = $this->pageWithDocuments();

        foreach ([
            "/admin/pages/{$page->id}/preview",
            "/admin/cms-canary/pages/{$page->id}",
        ] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
            $this->actingAs(User::factory()->create(['is_admin' => false]), 'web')
                ->get($url)
                ->assertRedirect(route('admin.login'));
        }
    }

    public function test_saved_preview_and_canary_embed_the_saved_draft_with_real_shell_and_no_hidden_sections(): void
    {
        $product = $this->product('saved-product', [
            'brand' => 'Saved Brand',
            'subtitle' => 'Public summary',
            'description_html' => '<p>private description</p>',
            'cost_price' => 1234,
            'barcode' => 'PRIVATE-BARCODE',
        ]);
        $page = $this->pageWithDocuments(
            draft: $this->document('Saved draft title', 'saved-draft', [
                $this->productGrid('visible-products', [(string) $product->id]),
                $this->richText('hidden-secret', 'HIDDEN DRAFT SECRET', false),
            ]),
            published: $this->document('Old published title', 'old-published', [
                $this->richText('published-copy', 'OLD PUBLISHED COPY'),
            ]),
        );
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ([
            "/admin/pages/{$page->id}/preview",
            "/admin/cms-canary/pages/{$page->id}",
        ] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();
            $response->assertSee('Page content is unavailable.')
                ->assertDontSee('Page preview unavailable.');
            $payload = $this->runtimePayload($response);

            $this->assertSame('Saved draft title', $payload['page']['document']['title']);
            $this->assertSame('draft', $payload['page']['stage']);
            $this->assertSame('preview', $payload['context']['mode']);
            $this->assertSame(['visible-products'], array_column($payload['page']['document']['sections'], 'id'));
            $this->assertSame([
                'id', 'title', 'slug', 'brand', 'summary', 'images', 'tags', 'basePrice', 'baseCompareAtPrice',
            ], array_keys($payload['catalog']['products'][0]));
            $this->assertStringNotContainsString('HIDDEN DRAFT SECRET', $response->getContent());
            $this->assertStringNotContainsString('OLD PUBLISHED COPY', $response->getContent());
            $response->assertSee('data-mega-nav', false)
                ->assertSee('ATELIER Design Studio Inc.', false)
                ->assertDontSee('AURORA COMMERCE', false);
        }
    }

    public function test_published_controller_uses_only_the_old_published_snapshot_while_a_newer_draft_exists(): void
    {
        $page = $this->pageWithDocuments(
            status: 'published',
            draft: $this->document('New draft title', 'new-draft', [
                $this->richText('draft-copy', 'NEW DRAFT SECRET'),
            ]),
            published: $this->document('Published title', 'published-page', [
                $this->richText('public-copy', 'PUBLIC SNAPSHOT COPY'),
                $this->richText('hidden-public', 'HIDDEN PUBLIC SECRET', false),
            ]),
        );

        $response = TestResponse::fromBaseResponse(app(StorefrontPageController::class)($page));
        $response->assertOk();
        $payload = $this->runtimePayload($response);

        $this->assertSame('Published title', $payload['page']['document']['title']);
        $this->assertSame('published', $payload['page']['stage']);
        $this->assertSame('public', $payload['context']['mode']);
        $this->assertSame(['public-copy'], array_column($payload['page']['document']['sections'], 'id'));
        $response->assertSee('PUBLIC SNAPSHOT COPY', false)
            ->assertDontSee('NEW DRAFT SECRET', false)
            ->assertDontSee('HIDDEN PUBLIC SECRET', false);
    }

    public function test_preview_serializes_empty_form_contract_maps_as_json_objects(): void
    {
        $page = $this->pageWithDocuments(draft: $this->document('No forms', 'no-forms', [
            $this->richText('visible-copy', 'VISIBLE COPY'),
        ]));

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/admin/pages/{$page->id}/preview")
            ->assertOk();

        preg_match('/<script[^>]*id="cms-page-runtime"[^>]*>(.*?)<\/script>/s', $response->getContent(), $matches);
        $payload = json_decode($matches[1] ?? '', false, 512, JSON_THROW_ON_ERROR);

        $this->assertIsObject($payload->forms->contact);
        $this->assertIsObject($payload->forms->newsletter);
    }

    public function test_published_controller_returns_not_found_for_draft_archived_or_missing_published_snapshot(): void
    {
        foreach (['draft', 'archived'] as $status) {
            $page = $this->pageWithDocuments(status: $status);

            try {
                app(StorefrontPageController::class)($page);
                $this->fail("Expected {$status} page to be unavailable.");
            } catch (NotFoundHttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }

        $missing = $this->pageWithDocuments(status: 'published', published: null);
        $this->expectException(NotFoundHttpException::class);
        app(StorefrontPageController::class)($missing);
    }

    public function test_invalid_runtime_reference_is_stable_and_corrupt_contract_data_never_leaks_raw_content(): void
    {
        $hiddenInvalid = $this->productGrid('hidden-invalid-product', ['888888']);
        $hiddenInvalid['isVisible'] = false;
        $page = $this->pageWithDocuments(draft: $this->document('Invalid runtime', 'invalid-runtime', [
            $hiddenInvalid,
            $this->productGrid('invalid-product', ['999999']),
        ]));
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)
            ->get("/admin/pages/{$page->id}/preview")
            ->assertOk();
        $payload = $this->runtimePayload($response);
        $this->assertArrayHasKey('sections.0.productIds', $payload['runtime']['errors']);
        $this->assertArrayNotHasKey('sections.1.productIds', $payload['runtime']['errors']);
        $this->assertSame(['invalid-product'], array_column($payload['page']['document']['sections'], 'id'));
        $this->assertSame([], $payload['catalog']['products']);

        $corrupt = '{"title":"LEAK-ME","slug":"bad","template":"landing","sections":[],"unknown":"APP_KEY=secret"}';
        DB::table('page_documents')
            ->where('page_id', $page->id)
            ->where('stage', PageDocument::STAGE_DRAFT)
            ->update(['document' => $corrupt]);

        $this->actingAs($admin)
            ->get("/admin/pages/{$page->id}/preview")
            ->assertStatus(422)
            ->assertSee('Page preview unavailable.')
            ->assertDontSee('LEAK-ME', false)
            ->assertDontSee('APP_KEY', false)
            ->assertDontSee('exception', false);
    }

    public function test_bootstrap_json_cannot_break_out_of_its_non_executable_element_and_optional_seo_is_safe(): void
    {
        $hostileTitle = '</script><script>globalThis.pwned=true</script>'."\u{2028}\u{2029}";
        $document = $this->document($hostileTitle, 'safe-json', [
            $this->richText('safe-copy', 'SAFE COPY'),
        ]);
        unset($document['seoTitle'], $document['seoDescription']);
        $page = $this->pageWithDocuments(draft: $document);

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/admin/pages/{$page->id}/preview")
            ->assertOk()
            ->assertDontSee('</script><script>globalThis.pwned=true</script>', false)
            ->assertSee('type="application/json"', false);

        $payload = $this->runtimePayload($response);
        $this->assertSame($hostileTitle, $payload['page']['document']['title']);
    }

    public function test_missing_public_entry_returns_an_intentional_assets_unavailable_response(): void
    {
        $manifest = $this->safeManifest();
        unset($manifest['src/public-page.tsx']);
        $this->writeManifest($manifest);
        $page = $this->pageWithDocuments();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/admin/pages/{$page->id}/preview")
            ->assertStatus(503)
            ->assertSee('Storefront page assets are unavailable.')
            ->assertDontSee('exception', false);
    }

    public function test_manifest_resolver_resolves_both_entries_and_shared_import_assets(): void
    {
        $resolver = new ViteManifestEntry($this->manifestPath, $this->manifestDirectory);

        $this->assertSame([
            'file' => 'assets/main.js',
            'css' => ['assets/main.css', 'assets/shared.css'],
            'imports' => ['assets/shared.js'],
        ], $resolver->resolve('src/main.tsx'));
        $this->assertSame([
            'file' => 'assets/public.js',
            'css' => ['assets/public.css', 'assets/shared.css'],
            'imports' => ['assets/shared.js'],
        ], $resolver->resolve('src/public-page.tsx'));
    }

    public function test_manifest_resolver_rejects_missing_malformed_and_unsafe_manifest_data(): void
    {
        $resolver = new ViteManifestEntry($this->manifestPath, $this->manifestDirectory);
        $safe = $this->safeManifest();
        $cases = [];

        $cases['malformed json'] = '{';
        $cases['non-object manifest'] = '[]';
        $cases['missing entry'] = json_encode(['src/main.tsx' => $safe['src/main.tsx']], JSON_THROW_ON_ERROR);

        foreach (['', '/assets/public.js', 'assets\\public.js', 'assets/../public.js', 'assets/public?.js'] as $unsafe) {
            $manifest = $safe;
            $manifest['src/public-page.tsx']['file'] = $unsafe;
            $cases["unsafe file {$unsafe}"] = json_encode($manifest, JSON_THROW_ON_ERROR);
        }

        foreach (['', '/assets/public.css', 'assets\\public.css', 'assets/../public.css', 'assets/public?.css'] as $unsafe) {
            $manifest = $safe;
            $manifest['src/public-page.tsx']['css'] = [$unsafe];
            $cases["unsafe css {$unsafe}"] = json_encode($manifest, JSON_THROW_ON_ERROR);
        }

        foreach (['', '/_shared.tsx', '..\\shared', '../shared'] as $unsafe) {
            $manifest = $safe;
            $manifest['src/public-page.tsx']['imports'] = [$unsafe];
            $cases["unsafe import key {$unsafe}"] = json_encode($manifest, JSON_THROW_ON_ERROR);
        }

        $manifest = $safe;
        $manifest['_shared.tsx']['file'] = '../shared.js';
        $cases['unsafe imported file'] = json_encode($manifest, JSON_THROW_ON_ERROR);

        $manifest = $safe;
        $manifest['_shared.tsx']['css'] = ['../shared.css'];
        $cases['unsafe imported css'] = json_encode($manifest, JSON_THROW_ON_ERROR);

        $manifest = $safe;
        $manifest['src/public-page.tsx']['imports'] = 'not-an-array';
        $cases['non-array imports'] = json_encode($manifest, JSON_THROW_ON_ERROR);

        foreach ($cases as $label => $contents) {
            File::put($this->manifestPath, $contents);
            $this->assertNull($resolver->resolve('src/public-page.tsx'), $label);
        }

        $this->writeManifest($safe);
        File::delete($this->manifestDirectory.'/assets/public.js');
        $this->assertNull($resolver->resolve('src/public-page.tsx'), 'missing entry file');

        File::delete($this->manifestPath);
        $this->assertNull($resolver->resolve('src/public-page.tsx'), 'missing manifest');
    }

    public function test_manifest_resolver_accepts_in_root_symlinks_and_rejects_external_targets_for_entry_import_and_css(): void
    {
        $this->externalAssetDirectory = storage_path('framework/testing/task-10-external-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->manifestDirectory.'/real');
        File::ensureDirectoryExists($this->externalAssetDirectory);

        foreach (['entry.js', 'import.js', 'page.css', 'import.css'] as $asset) {
            File::put($this->manifestDirectory.'/real/'.$asset, 'in-root');
            File::put($this->externalAssetDirectory.'/'.$asset, 'external');
        }

        $links = [];
        foreach (['entry.js', 'import.js', 'page.css', 'import.css'] as $asset) {
            $inRootLink = $this->manifestDirectory.'/assets/in-root-'.$asset;
            $externalLink = $this->manifestDirectory.'/assets/external-'.$asset;
            if (! @symlink($this->manifestDirectory.'/real/'.$asset, $inRootLink)
                || ! @symlink($this->externalAssetDirectory.'/'.$asset, $externalLink)) {
                $this->markTestSkipped('This filesystem cannot create file symlinks for the manifest containment proof.');
            }
            $links[$asset] = [
                'in' => 'assets/in-root-'.$asset,
                'out' => 'assets/external-'.$asset,
            ];
        }

        $resolver = new ViteManifestEntry($this->manifestPath, $this->manifestDirectory);
        $manifest = [
            'src/public-page.tsx' => [
                'file' => $links['entry.js']['in'],
                'css' => [$links['page.css']['in']],
                'imports' => ['_linked.tsx'],
            ],
            '_linked.tsx' => [
                'file' => $links['import.js']['in'],
                'css' => [$links['import.css']['in']],
                'imports' => [],
            ],
        ];
        $this->writeManifest($manifest);
        $this->assertSame([
            'file' => 'real/entry.js',
            'css' => ['real/page.css', 'real/import.css'],
            'imports' => ['real/import.js'],
        ], $resolver->resolve('src/public-page.tsx'));

        foreach ([
            'entry JS' => fn (array &$value) => $value['src/public-page.tsx']['file'] = $links['entry.js']['out'],
            'import JS' => fn (array &$value) => $value['_linked.tsx']['file'] = $links['import.js']['out'],
            'entry CSS' => fn (array &$value) => $value['src/public-page.tsx']['css'] = [$links['page.css']['out']],
            'import CSS' => fn (array &$value) => $value['_linked.tsx']['css'] = [$links['import.css']['out']],
        ] as $label => $mutate) {
            $unsafe = $manifest;
            $mutate($unsafe);
            $this->writeManifest($unsafe);
            $this->assertNull($resolver->resolve('src/public-page.tsx'), $label);
        }
    }

    public function test_manifest_resolver_returns_canonical_build_relative_paths_instead_of_case_aliases(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Case-alias proof applies to case-insensitive Windows filesystems.');
        }

        File::ensureDirectoryExists($this->manifestDirectory.'/Canonical');
        File::put($this->manifestDirectory.'/Canonical/Entry.js', 'entry');
        File::put($this->manifestDirectory.'/Canonical/Import.js', 'import');
        File::put($this->manifestDirectory.'/Canonical/Page.css', 'page css');
        File::put($this->manifestDirectory.'/Canonical/Import.css', 'import css');
        $this->writeManifest([
            'src/public-page.tsx' => [
                'file' => 'canonical/entry.js',
                'css' => ['canonical/page.css'],
                'imports' => ['_canonical.tsx'],
            ],
            '_canonical.tsx' => [
                'file' => 'canonical/import.js',
                'css' => ['canonical/import.css'],
                'imports' => [],
            ],
        ]);

        $resolver = new ViteManifestEntry($this->manifestPath, $this->manifestDirectory);
        $this->assertSame([
            'file' => 'Canonical/Entry.js',
            'css' => ['Canonical/Page.css', 'Canonical/Import.css'],
            'imports' => ['Canonical/Import.js'],
        ], $resolver->resolve('src/public-page.tsx'));
    }

    public function test_official_public_route_is_unchanged_and_no_unauthenticated_canary_route_exists(): void
    {
        $route = Route::getRoutes()->getByName('storefront.pages.show');

        $this->assertNotNull($route);
        $this->assertSame('pages/{page}', $route->uri());
        $this->assertSame(StorefrontController::class.'@page', $route->getActionName());
        $this->assertNull(Route::getRoutes()->getByName('storefront.cms-canary.pages.show'));
        $this->get('/cms-canary/pages/example')->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function runtimePayload(TestResponse $response): array
    {
        $matched = preg_match(
            '/<script[^>]*id="cms-page-runtime"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches,
        );
        $this->assertSame(1, $matched, 'Runtime bootstrap element was not found.');

        $wirePayload = json_decode($matches[1], false, 512, JSON_THROW_ON_ERROR);
        $this->assertIsObject($wirePayload->runtime->sections, 'Runtime sections must be a JSON object map.');
        $this->assertIsObject($wirePayload->runtime->errors, 'Runtime errors must be a JSON object map.');

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function pageWithDocuments(
        string $status = 'published',
        ?array $draft = null,
        ?array $published = [],
    ): Page {
        $page = Page::query()->create([
            'title' => 'Legacy identity',
            'slug' => 'legacy-identity-'.bin2hex(random_bytes(3)),
            'status' => $status,
            'is_published' => $status === 'published',
        ]);
        $suffix = bin2hex(random_bytes(4));
        $draft ??= $this->document('Saved draft title', 'saved-draft-'.$suffix, [
            $this->richText('visible-copy', 'SAVED DRAFT COPY'),
        ]);
        $this->storeDocument($page, PageDocument::STAGE_DRAFT, $draft);

        if ($published === []) {
            $published = $this->document('Published title', 'published-page-'.$suffix, [
                $this->richText('published-copy', 'PUBLISHED COPY'),
            ]);
        }
        if ($published !== null) {
            $this->storeDocument($page, PageDocument::STAGE_PUBLISHED, $published);
        }

        return $page->fresh();
    }

    /** @param list<array<string, mixed>> $sections @return array<string, mixed> */
    private function document(string $title, string $slug, array $sections): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'description' => 'Saved document description',
            'template' => 'landing',
            'heroImage' => null,
            'seoTitle' => $title.' SEO',
            'seoDescription' => 'Saved SEO description',
            'sections' => $sections,
        ];
    }

    /** @return array<string, mixed> */
    private function richText(string $id, string $content, bool $visible = true): array
    {
        return [
            'id' => $id,
            'type' => 'rich_text',
            'title' => $content,
            'content' => $content,
            'isVisible' => $visible,
        ];
    }

    /** @param list<string> $productIds @return array<string, mixed> */
    private function productGrid(string $id, array $productIds): array
    {
        return [
            'id' => $id,
            'type' => 'product_grid',
            'title' => 'Saved products',
            'isVisible' => true,
            'productIds' => $productIds,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function product(string $handle, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'handle' => $handle,
            'title' => 'Saved Product',
            'base_price' => 12900,
            'compare_at_price' => 15900,
            'status' => 'active',
            'tags' => ['Saved tag'],
        ], $attributes));
    }

    /** @param array<string, mixed> $document */
    private function storeDocument(Page $page, string $stage, array $document): void
    {
        $page->documents()->create([
            'stage' => $stage,
            'slug' => $document['slug'],
            'schema_version' => PageDocumentContract::VERSION,
            'lock_version' => 1,
            'content_hash' => PageDocumentHasher::hash($document),
            'document' => $document,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function safeManifest(): array
    {
        return [
            'src/main.tsx' => [
                'file' => 'assets/main.js',
                'css' => ['assets/main.css'],
                'imports' => ['_shared.tsx'],
            ],
            'src/public-page.tsx' => [
                'file' => 'assets/public.js',
                'css' => ['assets/public.css'],
                'imports' => ['_shared.tsx'],
            ],
            '_shared.tsx' => [
                'file' => 'assets/shared.js',
                'css' => ['assets/shared.css'],
            ],
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): void
    {
        File::put($this->manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
    }
}
