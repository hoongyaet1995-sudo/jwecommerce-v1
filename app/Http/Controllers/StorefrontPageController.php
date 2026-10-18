<?php

namespace App\Http\Controllers;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Domain\Cms\VisiblePageRuntimePayload;
use App\Models\Page;
use App\Support\ViteManifestEntry;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class StorefrontPageController extends Controller
{
    public function __construct(
        private readonly PageRuntimePayloadFactory $payloads,
        private readonly VisiblePageRuntimePayload $visiblePayload,
        private readonly ViteManifestEntry $manifest,
    ) {}

    public function __invoke(string $slug): Response|RedirectResponse
    {
        $home=app(\App\Domain\Cms\HomepageSelection::class)->id();
        if(request()->is('pages/*')&&$home&&\App\Models\PageDocument::where('page_id',$home)->where('stage','published')->where('slug',$slug)->exists())return redirect()->to('/'.(request()->query()?('?'.http_build_query(request()->query())):''),301);
        return $this->render(fn (): array => $this->payloads->forOfficialPublishedSlug($slug));
    }

    public function forPublished(Page $page): Response
    {
        return $this->render(fn (): array => $this->payloads->forCanary($page));
    }

    /** @param callable(): array<string, mixed> $payload */
    private function render(callable $payload): Response
    {
        try {
            $payload = $this->visiblePayload->filter($payload());
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            return response('Page unavailable.', 422);
        }

        $entry = $this->manifest->resolve('src/public-page.tsx');
        $document = $payload['page']['document'];
        if (isset($payload['page']['id'])) request()->attributes->set('searchPageId', (int)$payload['page']['id']);
        $quickAddProducts = app(\App\Domain\Cms\ProductListingPresentation::class)->prepare($payload);

        return response()->view('storefront.cms-page-react', [
            'serverHtml' => app(\App\Services\Search\CmsServerRenderer::class)->render($payload),
            'entry' => $entry,
            'payload' => $payload,
            'quickAddProducts' => $quickAddProducts,
            'title' => (($document['seoTitle'] ?? null) ?: ($document['title'] ?? 'Page')).' | '.app(\App\Support\ShopBrand::class)->name(),
            'description' => ($document['seoDescription'] ?? null) ?: ($document['description'] ?? null),
        ], $entry === null ? 503 : 200);
    }
}
