<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\Exceptions\CmsVersionConflict;
use App\Domain\Cms\PageDocumentJsonCodec;
use App\Domain\Cms\PageRevisionService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

final class PageHistoryController extends Controller
{
    public function index(Request $r, Page $page, PageRevisionService $history)
    {
        $input = $r->validate(['before' => 'sometimes|integer|min:1']);

        return response()->json(['data' => $history->list($page, (int) ($input['before'] ?? 0)), 'automaticLimit' => 100]);
    }

    public function show(Page $page, int $revision, PageRevisionService $history)
    {
        $v = $history->revision($page, $revision);

        return response()->json(['data' => ['id' => (string) $v->id, 'document' => app(PageDocumentJsonCodec::class)->decodeDocument($v->document)]]);
    }

    public function checkpoint(Request $r, Page $page, PageRevisionService $history)
    {
        $v = $r->validate(['lockVersion' => 'required|integer|min:1', 'name' => 'required|string|max:100', 'kind' => 'sometimes|in:checkpoint,ai_apply']);
        try {
            $id = $history->checkpoint($page, $v['lockVersion'], $v['kind'] ?? 'checkpoint', trim($v['name']));
        } catch (CmsVersionConflict $e) {
            return response()->json(['message' => 'This page has newer changes. Reload before continuing.'], 409);
        }

        return response()->json(['id' => (string) $id], 201);
    }

    public function restore(Request $r, Page $page, int $revision, PageRevisionService $history)
    {
        $v = $r->validate(['lockVersion' => 'required|integer|min:1']);
        try {
            $history->restore($page, $revision, $v['lockVersion']);
        } catch (CmsVersionConflict $e) {
            return response()->json(['message' => 'This page has newer changes. Nothing was restored. Reload the latest draft.'], 409);
        }

        return app(PageApiController::class)->showDraft($page->fresh());
    }
}
