<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingAvatarVersion;
use App\Models\User;
use App\Services\Marketing\MarketingAvatarAccess;
use App\Services\Marketing\MarketingAvatarLibrary;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MarketingAvatarController extends Controller
{
    public function assets()
    {
        return view('marketing.assets.index');
    }

    public function media()
    {
        return view('marketing.assets.media-planned');
    }

    public function index(Request $r)
    {
        return $this->listing($r);
    }

    private function listing(Request $r, ?string $selected = null)
    {
        $lib = app(MarketingAvatarLibrary::class);
        $avatars = $lib->all($r->user());
        $rows = [];
        $versions = MarketingAvatarVersion::whereIn('avatar_id', $avatars->pluck('id'))->orderByDesc('version')->get()->groupBy('avatar_id');
        foreach ($avatars as $a) {
            $history = $versions->get($a->id, collect());
            $v = $a->current_version ? $history->firstWhere('version', $a->current_version) : null;
            $valid = true;
            if ($v) {try {foreach ($v->references as $reference) $lib->path($reference);} catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {$valid = false;}}
            $rows[] = ['avatar' => $a, 'version' => $v, 'reference_valid'=>$valid, 'view_status'=>($a->status==='ready'&&!$valid)?'invalid':$a->status, 'history' => $history, 'can_write' => $a->library_id && $this->canWrite($r->user(), $a->library_id)];
        }

        return response()->view('marketing.avatars.index', ['rows' => $rows, 'selected' => $selected])->header('Cache-Control', 'private, no-store');
    }

    private function canWrite(User $actor, string $library): bool
    {
        try {
            app(MarketingAvatarAccess::class)->check($actor, $library, true);

            return true;
        } catch (HttpExceptionInterface) {
            return false;
        }
    }

    public function detail(Request $r, string $avatar)
    {
        app(MarketingAvatarLibrary::class)->avatar($r->user(), $avatar);

        return $this->listing($r, $avatar);
    }

    public function draft(Request $r)
    {
        $d = $r->validate(['request_id' => 'required|uuid', 'id' => 'nullable|uuid', 'name' => 'required|string|max:80', 'kind' => 'required|in:synthetic,real', 'description' => 'nullable|string|max:4000', 'look' => 'nullable|string|max:300', 'file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240', 'consent' => 'sometimes|accepted']);
        $lib = app(MarketingAvatarLibrary::class);
        $scope = isset($d['id']) ? $lib->avatar($r->user(), $d['id'], true)->library_id : $lib->home($r->user())->id;
        $a = $lib->saveDraft($r->user(), $scope, $d['request_id'], $d);
        if ($r->hasFile('file')) {
            $lib->reference($r->user(), $a->id, $r->file('file'), $r->boolean('consent'));
        }

        return redirect()->route('marketing.avatars.detail', $a->id)->with('status', 'Draft saved. No character has been generated or published.');
    }

    public function write(Request $r, string $avatar, string $action)
    {
        $lib = app(MarketingAvatarLibrary::class);
        $lib->avatar($r->user(), $avatar, true);
        if ($action === 'reference') {
            $r->validate(['file' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240', 'consent' => 'required|accepted', 'role' => 'sometimes|in:identity,look']);
            $lib->reference($r->user(), $avatar, $r->file('file'), true, $r->input('role', 'identity'));
        } elseif ($action === 'publish') {
            $d = $r->validate(['request_id' => 'required|uuid', 'checked' => 'required|accepted']);
            $lib->publish($r->user(), $avatar, $d['request_id'], true);
        } else {
            $lib->state($r->user(), $avatar, match ($action) {
                'archive' => 'archived','restore' => 'ready','revoke' => 'revoked',default => abort(404)
            });
        }

        return redirect()->route('marketing.avatars.detail', $avatar)->with('status', 'Avatar template updated. Existing projects retain their selected version.');
    }

    public function image(Request $r, string $avatar, int $version, string $role)
    {
        $lib = app(MarketingAvatarLibrary::class);
        $a = $lib->avatar($r->user(), $avatar);
        // Authorized library viewers may inspect historical/draft references; this never authorizes generation.
        $ref = $version === 0 ? ($a->draft['references'][$role] ?? null) : (MarketingAvatarVersion::where('avatar_id', $avatar)->where('version', $version)->firstOrFail()->references[$role] ?? null);
        abort_unless($ref,404);

        return response()->file($lib->path($ref),['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
