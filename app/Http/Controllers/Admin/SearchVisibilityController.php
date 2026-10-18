<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\PageDraftService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\Product;
use App\Models\SearchEntry;
use App\Models\SearchTask;
use App\Services\Search\SearchMetadata;
use App\Services\Search\SearchOptimization;
use App\Services\Search\SearchPublicationReview;
use App\Services\Search\SearchSettings;
use App\Services\Search\SearchRefreshService;
use App\Support\ShopBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SearchVisibilityController extends Controller
{
    private function owner(Request $r): void
    {
        abort_unless($r->user()?->is_super_admin, 403);
    }

    private function editor(Request $r): void {abort_unless($r->user()?->is_admin||$r->user()?->is_super_admin,403);}

    public function settings(Request $r, SearchSettings $settings, SearchOptimization $ai)
    {
        $this->owner($r);
        $connection = $ai->connection();

        return response()->json(['settings' => $settings->read(), 'origin' => $settings->origin(), 'brand' => app(ShopBrand::class)->name(), 'ready' => (bool) $connection, 'model' => $connection?->text_model, 'refresh' => app(SearchRefreshService::class)->summary(), 'counts' => ['product' => Product::count(), 'category' => Category::count(), 'collection' => Collection::count(), 'page' => Page::count()]])->header('Cache-Control', 'no-store');
    }

    public function refreshSeo(Request $request, SearchRefreshService $refresh)
    {
        $this->owner($request);

        return response()->json($request->isMethod('POST') ? $refresh->start($request->user()->id) : $refresh->summary(), $request->isMethod('POST') ? 202 : 200)->header('Cache-Control', 'no-store');
    }

    public function saveSettings(Request $r, SearchSettings $settings)
    {
        $this->owner($r);

        return response()->json(['settings' => $settings->save($r->validate(['version' => 'required|integer|min:0', 'mode' => ['required', Rule::in(['off', 'auto', 'review'])], 'preview' => 'required|boolean']))]);
    }

    public function advanceRefresh(Request $request, int $id, SearchRefreshService $refresh)
    {
        $this->owner($request);

        return response()->json($refresh->advance($id))->header('Cache-Control', 'no-store');
    }

    public function entity(Request $r, string $kind, int $id, SearchMetadata $metadata)
    {
        $this->editor($r);
        $data = $metadata->for($kind, $id);
        $task = SearchTask::where('kind', $kind)->where('entity_id', $id)->where('stage', $metadata->stage($kind))->latest('id')->first();
        $origin = app(SearchSettings::class)->origin();
        $path = $kind === 'page' ? '/pages/'.PageDocument::where('page_id', $id)->where('stage', 'draft')->value('slug') : $metadata->path($kind, $id);

        return response()->json(['metadata' => $data, 'facts' => $metadata->facts($kind, $id), 'task' => $task, 'origin' => $origin, 'url' => $origin.$path])->header('Cache-Control', 'no-store');
    }

    public function update(Request $r, string $kind, int $id, SearchMetadata $metadata)
    {
        $this->editor($r);
        $metadata->model($kind, $id);
        $data = $r->validate(['version' => 'required|integer|min:0', 'titleLocked' => 'required|boolean', 'descriptionLocked' => 'required|boolean', 'title' => 'nullable|string|max:255', 'description' => 'nullable|string|max:1000', 'excluded' => 'required|boolean']);
        foreach (['title', 'description'] as $field) {
            abort_if($data[$field.'Locked'] && trim($data[$field] ?? '') === '', 422, 'Enter override text or turn off the override.');
        }
        DB::transaction(function () use ($metadata, $kind, $id, $data) {
            $model = $metadata->model($kind, $id);
            $model->newQuery()->lockForUpdate()->findOrFail($id);
            $entry = SearchEntry::firstOrNew(['kind' => $kind, 'entity_id' => $id, 'stage' => $metadata->stage($kind)]);
            abort_if(($entry->exists ? $entry->version : 0) !== $data['version'], 409, 'Search information changed. Reload before saving.');
            $entry->fill(['title_locked' => $data['titleLocked'], 'description_locked' => $data['descriptionLocked'], 'excluded' => $data['excluded'], 'title' => $data['titleLocked'] ? $data['title'] : $entry->title, 'description' => $data['descriptionLocked'] ? $data['description'] : $entry->description, 'version' => $data['version'] + 1])->save();
            if ($kind === 'page') {
                $draft = PageDocument::where('page_id', $id)->where('stage', 'draft')->lockForUpdate()->firstOrFail();
                $document = $draft->document;
                $document['seoSettings'] = ['titleLocked' => $data['titleLocked'], 'descriptionLocked' => $data['descriptionLocked'], 'excluded' => $data['excluded']];
                if ($data['titleLocked']) {
                    $document['seoTitle'] = $data['title'];
                }if ($data['descriptionLocked']) {
                    $document['seoDescription'] = $data['description'];
                }app(PageDraftService::class)->save($model, $document, $draft->lock_version);
            }
            if ($kind !== 'page') {
                $values = [];
                if ($data['titleLocked']) {
                    $values['seo_title'] = $data['title'];
                }if ($data['descriptionLocked']) {
                    $values['seo_description'] = $data['description'];
                }if ($values) {
                    $model->forceFill($values)->saveQuietly();
                }
            }
        });

        return $this->entity($r, $kind, $id, $metadata);
    }

    public function optimize(Request $r, string $kind, int $id, SearchOptimization $ai)
    {
        $this->editor($r);
        $data = $r->validate(['replaceLegacy' => 'sometimes|boolean', 'retry' => 'sometimes|boolean']);
        $task = $ai->request($kind, $id, 'live', $data['replaceLegacy'] ?? false, $r->user()->id, $data['retry'] ?? false);
        abort_unless($task, 422, 'Enable Search optimization in AI Connect and website preferences, or unlock a metadata field.');

        return response()->json(['task' => $task], 202);
    }

    public function apply(Request $r, int $task, SearchOptimization $ai)
    {
        $this->editor($r);
        $ai->apply(SearchTask::findOrFail($task));

        return response()->json(['task' => SearchTask::findOrFail($task)]);
    }

    public function batch(Request $r, string $kind, SearchMetadata $metadata, SearchOptimization $ai)
    {
        $this->owner($r);
        abort_unless(in_array($kind, ['product', 'category', 'collection'], true), 404);
        $data = $r->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|min:1', 'confirm' => 'required|boolean']);
        $ids = array_values(array_unique($data['ids']));
        foreach ($ids as $id) {
            $metadata->model($kind, $id);
        }
        if (! $data['confirm']) {
            return response()->json(['count' => count($ids), 'ids' => $ids, 'model' => $ai->connection()?->text_model, 'ready' => (bool) $ai->connection(), 'maximumRequests' => count($ids), 'cost' => null]);
        }
        abort_if(app(SearchSettings::class)->read()['mode'] === 'off' || ! $ai->connection(), 422, 'Search AI is not enabled.');
        $tasks = [];
        foreach ($ids as $id) {
            $task = $ai->request($kind, $id, 'live', true, $r->user()->id);
            if ($task) {
                $tasks[] = $task->id;
            }
        }

        return response()->json(['taskIds' => $tasks, 'count' => count($tasks)], 202);
    }

    public function tasks(Request $r)
    {
        $this->owner($r);

        return response()->json(SearchTask::latest('id')->paginate(25))->header('Cache-Control', 'no-store');
    }

    public function review(Request $r, int $id, SearchPublicationReview $review)
    {
        $this->editor($r);
        $page = Page::findOrFail($id);
        if ($r->isMethod('GET')) {
            return response()->json($review->inspect($page))->header('Cache-Control', 'no-store');
        }
        $data = $r->validate(['lockVersion' => 'required|integer|min:1', 'confirmations' => 'required|array|size:3', 'confirmations.*' => 'accepted']);

        return response()->json($review->confirm($page, $data['lockVersion'], $r->user()->id));
    }

    public function catalog(Request $r)
    {
        $this->owner($r);
        $data = $r->validate(['kind' => ['required', Rule::in(['product', 'category', 'collection'])], 'q' => 'nullable|string|max:200']);
        $class = match ($data['kind']) {
            'product' => Product::class,'category' => Category::class,'collection' => Collection::class
        };
        $column = $data['kind'] === 'category' ? 'name' : 'title';
        $query = $class::query();
        if (filled($data['q'] ?? '')) {
            $query->where($column, 'like', '%'.addcslashes($data['q'], '%_\\').'%');
        }
        $page = $query->orderBy('id')->paginate(25);
        $page->setCollection($page->getCollection()->map(fn ($m) => ['id' => $m->id, 'name' => $m->$column, 'status' => $m->status ?? ($m->is_active ? 'active' : 'draft')]));

        return response()->json($page)->header('Cache-Control','no-store');
    }
}
