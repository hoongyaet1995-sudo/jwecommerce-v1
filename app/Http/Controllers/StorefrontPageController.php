<?php

namespace App\Http\Controllers;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Domain\Cms\VisiblePageRuntimePayload;
use App\Models\Page;
use App\Support\ViteManifestEntry;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class StorefrontPageController extends Controller
{
    public function __construct(
        private readonly PageRuntimePayloadFactory $payloads,
        private readonly VisiblePageRuntimePayload $visiblePayload,
        private readonly ViteManifestEntry $manifest,
    ) {}

    public function __invoke(Page $page): Response
    {
        try {
            $payload = $this->visiblePayload->filter($this->payloads->forPublished($page));
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            return response('Page unavailable.', 422);
        }

        $entry = $this->manifest->resolve('src/public-page.tsx');
        $document = $payload['page']['document'];

        return response()->view('storefront.cms-page-react', [
            'entry' => $entry,
            'payload' => $payload,
            'title' => (($document['seoTitle'] ?? null) ?: ($document['title'] ?? 'Page')).' | JW Atelier',
            'description' => ($document['seoDescription'] ?? null) ?: ($document['description'] ?? null),
        ], $entry === null ? 503 : 200);
    }
}
