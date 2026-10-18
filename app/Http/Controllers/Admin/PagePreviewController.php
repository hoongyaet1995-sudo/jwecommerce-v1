<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Domain\Cms\VisiblePageRuntimePayload;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\ViteManifestEntry;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class PagePreviewController extends Controller
{
    public function __construct(
        private readonly PageRuntimePayloadFactory $payloads,
        private readonly VisiblePageRuntimePayload $visiblePayload,
        private readonly ViteManifestEntry $manifest,
    ) {}

    public function __invoke(Page $page): Response
    {
        try {
            $payload = $this->visiblePayload->filter($this->payloads->forDraft($page));
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            return response('Page preview unavailable.', 422);
        }

        $entry = $this->manifest->resolve('src/public-page.tsx');
        $document = $payload['page']['document'];
        $quickAddProducts = app(\App\Domain\Cms\ProductListingPresentation::class)->prepare($payload);

        return response()->view('storefront.cms-page-react', [
            'serverHtml'=>app(\App\Services\Search\CmsServerRenderer::class)->render($payload),
            'entry' => $entry,
            'payload' => $payload,
            'quickAddProducts' => $quickAddProducts,
            'title' => (($document['seoTitle'] ?? null) ?: ($document['title'] ?? 'Page')).' | '.app(\App\Support\ShopBrand::class)->name(),
            'description' => ($document['seoDescription'] ?? null) ?: ($document['description'] ?? null),
        ], $entry === null ? 503 : 200);
    }
}
