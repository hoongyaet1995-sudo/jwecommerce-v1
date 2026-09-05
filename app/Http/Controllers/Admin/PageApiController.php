<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\Exceptions\CmsPublicationBlocked;
use App\Domain\Cms\Exceptions\CmsRuntimeReferenceInvalid;
use App\Domain\Cms\Exceptions\CmsVersionConflict;
use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDocumentJsonCodec;
use App\Domain\Cms\PageDraftService;
use App\Domain\Cms\PagePublicationService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PageApiController extends Controller
{
    public function __construct(
        private readonly PageDraftService $drafts,
        private readonly PageCrudService $pages,
        private readonly PageDocumentJsonCodec $documents,
        private readonly PagePublicationService $publication,
    ) {}

    public function index(): JsonResponse
    {
        $homepageId = app(\App\Domain\Cms\HomepageSelection::class)->id();
        return response()->json([
            'data' => Page::query()
                ->whereHas('draftDocument')
                ->with(['draftDocument', 'publishedDocument'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Page $page): array => $this->pageSummary($page, $homepageId ?? ''))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $page = $this->pages->create($this->documents->decodeDocument($request->getContent()));
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        return response()->json(['data' => $this->pageSummary($page)], 201);
    }

    public function showDraft(Page $page): JsonResponse
    {
        $page->loadMissing(['draftDocument', 'publishedDocument']);

        return response()->json(['data' => $this->draftPayload($page)]);
    }

    /**
     * Backward route alias retained until the Task 13 route cleanup. It now
     * uses the canonical draft contract and never writes legacy page sections.
     */
    public function update(Request $request, Page $page): JsonResponse
    {
        return $this->updateDraft($request, $page);
    }

    public function updateDraft(Request $request, Page $page): JsonResponse
    {
        try {
            $input = Validator::make($this->documents->decodeDraftEnvelope($request->getContent()), [
                'lockVersion' => ['required', 'integer', 'min:0'],
                'document' => ['required'],
            ])->validate();
        } catch (ValidationException $exception) {
            return $this->validationError($exception, 'The page draft request is invalid.');
        }

        try {
            $document = $this->documents->normalizeDocumentToken($input['document']);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        try {
            $draft = $this->drafts->save(
                $page,
                $document,
                $input['lockVersion'],
            );
        } catch (CmsVersionConflict $exception) {
            return $this->versionConflict($exception);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        $page->setRelation('draftDocument', $draft);
        $page->load('publishedDocument');

        return response()->json(['data' => $this->draftPayload($page)]);
    }

    public function publish(Request $request, Page $page): JsonResponse
    {
        try {
            $input = Validator::make($request->all(), [
                'lockVersion' => ['required', 'integer', 'min:0'],
            ])->validate();
        } catch (ValidationException $exception) {
            return $this->validationError($exception, 'The page publication request is invalid.');
        }

        try {
            $page = $request->routeIs('admin.api.pages.homepage')
                ? app(\App\Domain\Cms\HomepageSelection::class)->select($page, $input['lockVersion'])
                : $this->publication->publish($page, $input['lockVersion']);
        } catch (CmsVersionConflict $exception) {
            return $this->versionConflict($exception);
        } catch (CmsRuntimeReferenceInvalid $exception) {
            return $this->validationError(
                $exception,
                'The page cannot be published.',
                $exception->machineCode(),
            );
        } catch (CmsPublicationBlocked $exception) {
            return $this->validationError(
                $exception,
                'The page cannot be published.',
                $exception->machineCode(),
            );
        }

        return response()->json(['data' => $this->pageSummary($page)]);
    }

    public function unpublish(Page $page): JsonResponse
    {
        return response()->json([
            'data' => $this->pageSummary($this->publication->unpublish($page)),
        ]);
    }

    public function archive(Page $page): JsonResponse
    {
        return response()->json([
            'data' => $this->pageSummary($this->publication->archive($page)),
        ]);
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->pages->delete($page);

        return response()->json(status: 204);
    }

    public function duplicate(Page $page): JsonResponse
    {
        try {
            $copy = $this->pages->duplicate($page);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        return response()->json(['data' => $this->pageSummary($copy)], 201);
    }

    /** @return array<string, mixed> */
    private function pageSummary(Page $page, ?string $homepageId = null): array
    {
        $draft = $this->requiredDraft($page);
        $published = $page->publishedDocument;
        $document = $draft->document;

        return array_merge($document, [
            'id' => (string) $page->getKey(),
            'isHomepage' => ($homepageId ?? app(\App\Domain\Cms\HomepageSelection::class)->id()) === (string) $page->getKey(),
            'status' => $page->status,
            'lockVersion' => $draft->lock_version,
            'hasPublishedSnapshot' => $published !== null,
            'hasUnpublishedChanges' => $this->hasUnpublishedChanges($draft, $published),
            'createdAt' => $page->created_at?->toDateString(),
            'updatedAt' => $draft->updated_at?->toDateString(),
        ]);
    }

    /** @return array<string, mixed> */
    private function draftPayload(Page $page): array
    {
        $draft = $this->requiredDraft($page);
        $published = $page->publishedDocument;

        return [
            'pageId' => (string) $page->getKey(),
            'status' => $page->status,
            'schemaVersion' => $draft->schema_version,
            'lockVersion' => $draft->lock_version,
            'contentHash' => $draft->content_hash,
            'hasPublishedSnapshot' => $published !== null,
            'hasUnpublishedChanges' => $this->hasUnpublishedChanges($draft, $published),
            'document' => $draft->document,
            'updatedAt' => $draft->updated_at?->toISOString(),
        ];
    }

    private function requiredDraft(Page $page): PageDocument
    {
        $draft = $page->draftDocument;

        abort_if($draft === null, 404, 'This page does not have a canonical draft document.');

        return $draft;
    }

    private function hasUnpublishedChanges(PageDocument $draft, ?PageDocument $published): bool
    {
        return $published === null || ! hash_equals($published->content_hash, $draft->content_hash);
    }

    private function validationError(
        ValidationException $exception,
        string $message = 'The page document is invalid.',
        ?string $code = null,
    ): JsonResponse {
        $errors = $exception->errors();
        $unsupported = collect($errors)
            ->flatten()
            ->contains('This field is not supported by the page contract.');

        return response()->json([
            'message' => $message,
            'code' => $code ?? ($unsupported ? 'unsupported_contract_field' : 'cms_validation_failed'),
            'errors' => $errors,
        ], 422);
    }

    private function versionConflict(CmsVersionConflict $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'cms_version_conflict',
            'errors' => [
                'lockVersion' => ['Reload or reconcile your local changes before saving again.'],
            ],
            'currentLockVersion' => $exception->currentLockVersion,
        ], 409);
    }
}
