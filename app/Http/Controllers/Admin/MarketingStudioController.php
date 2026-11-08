<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingAiRun;
use App\Models\MarketingAsset;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\Product;
use App\Services\Marketing\MarketingWorkflow;
use App\Services\Marketing\MarketingWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingStudioController extends Controller
{
    private function owner(Request $r): int
    {
        abort_unless($r->user()?->is_super_admin || ($r->isMethod('GET') && \App\Domain\Marketing\MarketingPermission::allows($r->user())), 403);

        return (int) $r->user()->id;
    }

    private function project(Request $r, string $id): MarketingProject
    {
        return MarketingProject::where('owner_id', $this->owner($r))->with(['assets', 'revisions', 'currentRevision'])->findOrFail($id);
    }

    public function home(Request $r)
    {
        $q = MarketingProject::where('owner_id', $this->owner($r));

        return view('marketing.home', ['recent' => (clone $q)->latest('updated_at')->limit(5)->get(), 'attention' => (clone $q)->whereIn('status', ['needs_information', 'needs_confirmation', 'failed'])->latest('updated_at')->get()]);
    }

    public function index(Request $r)
    {
        $input = $r->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|in:needs_information,needs_confirmation,prepared,processing,failed', 'page' => 'nullable|integer|min:1']);
        $q = MarketingProject::where('owner_id', $this->owner($r))->latest('updated_at');
        if ($search = ($input['q'] ?? '')) {
            $q->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')->orWhere('snapshot->title', 'like', '%'.$search.'%');
            });
        }
        if ($status = ($input['status'] ?? '')) {
            $q->where('status', $status);
        }

        return view('marketing.index', ['projects' => $q->paginate(15)->withQueryString()]);
    }

    public function create(Request $r)
    {
        $this->owner($r);
        $r->validate(['q' => 'nullable|string|max:150']);

        return view('marketing.create', ['products' => Product::with('images')->when($r->query('q'), fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))->orderBy('title')->limit(50)->get(['id', 'title', 'subtitle', 'status', 'purchase_mode'])]);
    }

    public function store(Request $r, MarketingWorkflow $flow)
    {
        $this->owner($r);
        $input = $r->validate(['source_type' => 'required|in:product,theme', 'product_id' => 'required_if:source_type,product|nullable|integer|exists:products,id', 'theme' => 'required_if:source_type,theme|nullable|string|min:5|max:2000']);
        $p = $flow->create($r->user(), $input);
        if (app(MarketingWriter::class)->ready($p->id)) {
            try {
                $flow->generate($p->id, $r->user()->id, $p->version, 'recommend', '', (string) Str::uuid());
            } catch (ValidationException $e) {
                return redirect()->route('marketing.show', $p->id)->withErrors($e->errors());
            }
        }

        return redirect()->route('marketing.show', $p->id)->with('message', 'Draft saved. Review the organized facts; confirm or adjust them before continuing.');
    }

    public function show(Request $r, string $project)
    {
        $p = $this->project($r, $project);
        $revision = $r->query('revision') ? MarketingRevision::where('project_id', $p->id)->findOrFail($r->query('revision')) : $p->currentRevision;

        return view('marketing.show', ['project' => $p, 'revision' => $revision, 'textReady' => app(MarketingWriter::class)->ready($p->id), 'failedRun' => MarketingAiRun::where('project_id', $p->id)->whereIn('status', ['failed', 'unknown'])->latest()->first(), 'sourceProducts' => ! $p->source_confirmed_at ? Product::orderBy('title')->limit(100)->get(['id', 'title']) : collect()]);
    }

    public function source(Request $r, string $project, MarketingWorkflow $flow)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer', 'source_type' => 'required|in:product,theme', 'product_id' => 'required_if:source_type,product|nullable|integer|exists:products,id', 'theme' => 'required_if:source_type,theme|nullable|string|min:5|max:2000']);
        $flow->switchSource($project, $r->user()->id, $d['version'], $d);

        return back()->with('message', 'Source changed. Your supplemental notes were kept; review whether they still apply.');
    }

    public function interrupted(Request $r, string $project, MarketingWorkflow $flow)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer', 'acknowledge' => 'required|accepted']);
        $flow->interrupted($project, $r->user()->id, $d['version']);

        return back()->with('message', 'Request marked as outcome unknown. No provider call was repeated.');
    }

    public function brief(Request $r, string $project, MarketingWorkflow $flow)
    {
        $p = $this->project($r, $project);
        $base = $r->validate(['version' => 'required|integer|min:1', 'step' => 'required|in:source,goal,audience']);
        $step = $base['step'];
        $rules = match ($step) {
            'source' => ['name' => 'required|string|max:150', 'source_confirmed' => 'required|accepted', 'facts' => 'nullable|string|max:5000', 'facts_confirmed' => 'required|accepted'],
            'goal' => ['goal' => 'required|in:awareness,introduction,promotion,enquiry,purchase', 'cta' => 'required|string|max:150', 'destination' => 'nullable|url:http,https|max:500'],
            'audience' => ['audience' => 'required|string|max:500', 'market' => 'required|string|max:100', 'language' => 'required|string|max:80', 'platforms' => 'required|array|min:1|max:4', 'platforms.*' => 'in:Facebook,Instagram,TikTok,YouTube'],
        };
        abort_if($step !== 'source' && ! $p->brief['source_confirmed'], 422, 'Confirm source information first.');
        abort_if($step === 'audience' && ! $p->brief['goal_confirmed'], 422, 'Confirm the goal first.');
        $data = $r->validate($rules);
        $version = $base['version'];
        $name = $data['name'] ?? $p->name;
        unset($data['name']);
        if ($step === 'source') {
            $data['source_confirmed'] = true;
            $data['facts_confirmed'] = true;
            $data['facts'] = $data['facts'] ?? '';
            $data['goal_confirmed'] = false;
            $data['audience_confirmed'] = false;
        }
        if ($step === 'goal') {
            $data['goal_confirmed'] = true;
            $data['audience_confirmed'] = false;
            $data['destination'] = $data['destination'] ?? '';
        }
        if ($step === 'audience') {
            $data['audience_confirmed'] = true;
        }
        DB::transaction(function () use ($p, $r, $flow, $version, $data, $name) {
            $flow->saveBrief($p->id, $r->user()->id, $version, $data);
            MarketingProject::whereKey($p->id)->update(['name' => $name]);
        });

        return redirect()->route('marketing.show', $project)->with('message', 'Saved. Review the next recommendation. Changes to the brief require fresh concepts and confirmation.');
    }

    public function concept(Request $r, string $project, MarketingWorkflow $flow)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer', 'index' => 'required|integer|min:0|max:2']);
        $flow->selectConcept($project, $r->user()->id, $d['version'], $d['index']);

        return back()->with('message', 'Concept selected. Prepare the complete storyboard next.');
    }

    public function generate(Request $r, string $project, MarketingWorkflow $flow)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer', 'request_id' => 'required|uuid', 'action' => 'required|in:recommend,concepts,storyboard,revise', 'feedback' => 'required_if:action,revise|nullable|string|max:2000', 'parent_id' => 'nullable|uuid']);
        $flow->generate($project, $r->user()->id, $d['version'], $d['action'], $d['feedback'] ?? '', $d['request_id'], $d['parent_id'] ?? null);

        return back()->with('message', 'Text preparation finished. Review the saved result; nothing was generated as video or published.');
    }

    public function confirm(Request $r, string $project, MarketingWorkflow $flow)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer', 'revision_id' => 'required|uuid', 'content_confirmation' => 'required|accepted']);
        $flow->confirm($project, $r->user()->id, $d['version'], $d['revision_id']);

        return redirect()->route('marketing.show', $project)->with('message', 'Script and storyboard confirmed. This does not authorize paid production or publication.');
    }

    public function upload(Request $r, string $project, MarketingWorkflow $flow)
    {
        $p = $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer', 'asset' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,webm|max:30720', 'permission' => 'required|accepted']);
        $file = $r->file('asset');
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'application/mp4', 'video/webm'], true), 422);
        if (str_starts_with($mime, 'image/')) {
            $size = @getimagesize($file->getRealPath());
            abort_unless($size && $size[0] <= 8000 && $size[1] <= 8000 && $size[0] * $size[1] <= 40000000 && $file->getSize() <= 10 * 1024 * 1024, 422, 'Image is too large or invalid.');
        }
        $path = $file->store('marketing/'.$p->id, 'local');
        try {
            DB::transaction(function () use ($p, $r, $d, $flow, $file, $mime, $path) {
                $p = $flow->locked($p->id, $r->user()->id, $d['version']);
                MarketingAsset::create(['project_id' => $p->id, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 180), 'path' => $path, 'mime' => $mime, 'size' => $file->getSize(), 'authorized' => true]);
                $p->update(['version' => $p->version + 1]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('message', 'Private project material saved. Update the storyboard if this resolves a material gap.');
    }

    public function asset(Request $r, string $project, string $asset)
    {
        $p = $this->project($r, $project);
        $a = $p->assets()->findOrFail($asset);

        app(\App\Services\Marketing\MarketingMediaReuse::class)->assertLinked($a);
        return Storage::disk('local')->response($a->path, $a->name, ['Content-Type' => $a->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
