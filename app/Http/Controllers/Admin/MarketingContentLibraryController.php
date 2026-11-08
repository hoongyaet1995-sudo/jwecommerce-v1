<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\MarketingPermission as Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Marketing\MarketingMediaAccess;
use App\Services\Marketing\MarketingMediaRegister;
use App\Services\Marketing\MarketingPostMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingContentLibraryController extends Controller
{
    private function actor(Request $r, bool $edit = false): User
    {
        abort_unless(Permission::allows($r->user(), $edit ? Permission::EDIT : Permission::VIEW), 403);

        return $r->user();
    }

    public function index(Request $r, string $kind = 'all')
    {
        $this->actor($r);

        return response()->view('marketing.library.index', ['kind' => $kind])->header('Cache-Control', 'private, no-store');
    }

    public function settings(Request $r)
    {
        $this->actor($r);

        return response()->view('marketing.library.settings')->header('Cache-Control', 'private, no-store');
    }

    public function studio(Request $r)
    {
        $this->actor($r);

        return response()->view('marketing.library.video-studio')->header('Cache-Control', 'private, no-store');
    }

    public function data(Request $r, MarketingMediaAccess $access)
    {
        $actor = $this->actor($r);
        $d = $r->validate(['kind' => 'nullable|in:all,image,video', 'q' => 'nullable|string|max:100', 'origin' => 'nullable|in:all,ai,uploaded', 'selection' => 'nullable|in:all,selected,not-selected,not-reviewed', 'sort' => 'nullable|in:newest,name', 'page' => 'nullable|integer|min:1|max:100000']);
        $base = $access->query($actor);
        $counts = (clone $base)->selectRaw('kind, COUNT(*) AS total')->groupBy('kind')->pluck('total', 'kind');
        $q = (clone $base)->when(($d['kind'] ?? 'all') !== 'all', fn ($q) => $q->where('kind', $d['kind']))->when(($d['origin'] ?? 'all') !== 'all', fn ($q) => $q->where('origin', $d['origin']))->when(($d['selection'] ?? 'all') !== 'all', fn ($q) => $q->where('selection', $d['selection']));
        if (trim($d['q'] ?? '') !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($d['q'])).'%';
            $q->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$term])->orWhereRaw("project_name LIKE ? ESCAPE '!'", [$term]));
        }
        if (($d['sort'] ?? 'newest') === 'name') {
            $q->orderBy('title');
        } else {
            $q->orderByDesc('occurred_at');
        }$q->orderByDesc('id');
        $page = $q->paginate(24);
        $assets = $page->getCollection()->map(function ($a) use ($actor, $access) {
            $available = (bool) app(MarketingMediaRegister::class)->localPath($a->path, $a->disk);
            $source = $a->project_id ? route('marketing.show', $a->project_id) : ($a->avatar_id ? route('marketing.avatars.detail', $a->avatar_id) : ($a->source_type === 'marketing_daily_draft_runs' ? url('/admin/marketing/daily-drafts/'.$a->source_id) : null));

            return ['id' => $a->id, 'title' => $a->title, 'kind' => $a->kind, 'origin' => $a->origin, 'stage' => $a->stage, 'project' => $a->project_name, 'selection' => $a->selection, 'width' => $a->width, 'height' => $a->height, 'seconds' => $a->seconds, 'bytes' => $a->bytes, 'mime' => $a->mime, 'created_at' => $a->occurred_at?->toIso8601String(), 'available' => $available, 'can_download' => $access->downloadable($actor, $a), 'src' => route('marketing.library.file', $a->id), 'download' => route('marketing.library.file', ['asset' => $a->id, 'download' => 1]), 'source_url' => $source];
        });

        return response()->json(['assets' => $assets, 'counts' => ['all' => (int) $counts->sum(), 'image' => (int) ($counts['image'] ?? 0), 'video' => (int) ($counts['video'] ?? 0)], 'total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()])->header('Cache-Control', 'private, no-store');
    }

    public function file(Request $r, string $asset, MarketingMediaAccess $access)
    {
        [$row,$path] = $access->file($this->actor($r), $asset);
        $headers = ['Content-Type' => $row->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if ($r->boolean('download')) {
            abort_unless($access->downloadable($r->user(), $row), 403, 'Approve this completed video in its source project before downloading.');
            $ext = match ($row->mime) {
                'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp','image/gif' => 'gif','video/webm' => 'webm','video/quicktime' => 'mov',default => 'mp4'
            };

            return response()->download($path, Str::slug($row->title).'.'.$ext, $headers);
        }

        return response()->file($path, $headers);
    }

    public function upload(Request $r, MarketingPostMedia $media)
    {
        $actor = $this->actor($r, true);
        $r->validate(['files' => 'required|array|min:1|max:10', 'files.*' => 'required|file|mimetypes:image/jpeg,image/png,image/webp,video/mp4|max:65536']);
        $ids = [];
        foreach ($r->file('files') as $file) {
            $ids[] = $media->store($actor->id, $file);
        }

        return response()->json(['message' => 'Assets uploaded. Original files are retained.', 'assets' => $ids],201)->header('Cache-Control','private, no-store');
    }
}
