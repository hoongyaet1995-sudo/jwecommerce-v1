<?php

namespace App\Services\Marketing;

use App\Models\MarketingAvatar;
use App\Models\MarketingAvatarLibrary as Library;
use App\Models\MarketingAvatarLibraryMember;
use App\Models\MarketingAvatarVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MarketingAvatarLibrary
{
    public function home(User $actor): Library
    {
        app(MarketingAvatarAccess::class)->libraries($actor);

        return Library::firstOrCreate(['owner_id' => $actor->id], ['name' => '我的人物模板']);
    }

    public function member(User $actor, string $library, int $user, string $role): void
    {
        $l = app(MarketingAvatarAccess::class)->check($actor, $library);
        abort_unless($l->owner_id === $actor->id && in_array($role, ['manager', 'editor', 'viewer'], true), 403);
        $u = User::findOrFail($user);
        abort_unless($u->is_admin || $u->is_super_admin, 422);
        MarketingAvatarLibraryMember::updateOrCreate(['library_id' => $library, 'user_id' => $user], ['role' => $role]);
    }

    public function all(User $actor): Collection
    {
        return MarketingAvatar::whereIn('library_id', app(MarketingAvatarAccess::class)->libraries($actor))->orderBy('created_at')->get();
    }

    public function avatar(User $actor, string $id, bool $write = false): MarketingAvatar
    {
        $a = MarketingAvatar::findOrFail($id);
        app(MarketingAvatarAccess::class)->check($actor, $a->library_id, $write);

        return $a;
    }

    public function saveDraft(User $actor, string $library, string $request, array $input): MarketingAvatar
    {
        app(MarketingAvatarAccess::class)->check($actor, $library, true);
        Validator::make($input, ['name' => 'required|string|max:80', 'kind' => 'required|in:synthetic,real', 'description' => 'nullable|string|max:4000', 'look' => 'nullable|string|max:300'])->validate();
        abort_unless(Str::isUuid($request), 422);
        $clean = array_intersect_key($input, array_flip(['name', 'kind', 'description', 'look']));
        $digest = hash('sha256', json_encode($clean, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $library, $request, $input, $clean, $digest) {
            if (! empty($input['id'])) {
                $a = $this->avatar($actor, $input['id'], true);
                abort_unless($a->library_id === $library && $a->status !== 'revoked', 409);
                $a->update(['name' => $clean['name'], 'draft' => array_replace($a->draft, $clean)]);

                return $a;
            }
            $old = MarketingAvatar::where('library_id', $library)->where('request_id', $request)->first();
            if ($old) {
                abort_unless(hash_equals($old->request_digest, $digest), 409);

                return $old;
            }

            return MarketingAvatar::create(['library_id' => $library, 'request_id' => $request, 'request_digest' => $digest, 'name' => $clean['name'], 'kind' => $clean['kind'], 'draft' => $clean, 'status' => 'draft']);
        });
    }

    public function reference(User $actor, string $id, UploadedFile $file, bool $consent, string $role = 'identity'): MarketingAvatar
    {
        $a = $this->avatar($actor, $id, true);
        abort_unless($a->status !== 'revoked' && $consent && in_array($role, ['identity', 'look'], true), 422, '请确认人物参考的使用权。');
        Validator::make(['file' => $file], ['file' => 'required|image|mimes:png,jpg,jpeg,webp|max:10240'])->validate();
        $info = @getimagesize($file->getRealPath());
        abort_unless($info && min($info[0], $info[1]) >= 256 && $info[0] * $info[1] <= 40000000, 422, '人物参考需要清晰、单人的有效图片。');
        $hash = hash_file('sha256', $file->getRealPath());
        $path = 'marketing-avatar/'.$a->library_id.'/'.$hash.'.'.$file->guessExtension();
        $file->storeAs(dirname($path), basename($path), 'local');

        return DB::transaction(function () use ($a, $actor, $role, $path, $hash, $file) {
            $a = MarketingAvatar::lockForUpdate()->findOrFail($a->id);
            $d = $a->draft;
            $d['references'][$role] = ['path' => $path, 'sha256' => $hash, 'mime' => $file->getMimeType()];
            $d['rights'] = ['confirmed_by' => $actor->id, 'confirmed_at' => now()->toIso8601String(), 'consent' => true];
            $a->update(['draft' => $d]);

            return $a;
        });
    }

    public function path(array $reference): string
    {
        $root = realpath(Storage::disk('local')->path('marketing-avatar'));
        $file = realpath(Storage::disk('local')->path($reference['path'] ?? ''));
        abort_unless($root && $file && str_starts_with(strtolower($file), strtolower($root.DIRECTORY_SEPARATOR)) && hash_equals($reference['sha256'] ?? '', hash_file('sha256', $file)), 409, '人物参考文件已改变或不可用。');

        return $file;
    }

    public function publish(User $actor, string $id, string $request, bool $checked): MarketingAvatarVersion
    {
        abort_unless(Str::isUuid($request), 422);

        return DB::transaction(function () use ($actor, $id, $request, $checked) {
            $a = $this->avatar($actor, $id, true);
            $a = MarketingAvatar::lockForUpdate()->findOrFail($a->id);
            $d = $a->draft;
            abort_unless($a->status !== 'revoked' && $checked && ! empty($d['references']['identity']) && ! empty($d['rights']['consent']), 422, '请准备人物参考，并确认单人外观、参考一致性与使用范围。');
            foreach ($d['references'] as $ref) {
                $this->path($ref);
            }
            $snapshot = ['name' => $d['name'], 'kind' => $d['kind'], 'look' => $d['look'] ?? '沿用参考造型', 'references' => $d['references'], 'rights' => $d['rights']];
            $digest = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $old = MarketingAvatarVersion::where('avatar_id', $id)->where('request_id', $request)->first();
            if ($old) {
                abort_unless(hash_equals($old->request_digest, $digest), 409);

                return $old;
            }
            $identity = hash('sha256', json_encode([$snapshot['kind'], $snapshot['look'], array_map(fn ($r) => $r['sha256'], $snapshot['references'])], JSON_THROW_ON_ERROR));
            $v = MarketingAvatarVersion::create($snapshot + ['avatar_id' => $id, 'version' => $a->current_version + 1, 'request_id' => $request, 'request_digest' => $digest, 'identity_digest' => $identity]);
            $a->update(['current_version' => $v->version, 'kind' => $snapshot['kind'], 'status' => 'ready']);

            return $v;
        });
    }

    public function resolveVersion(User $actor, string $id, int $version, bool $bound = false): MarketingAvatarVersion
    {
        $a = $this->avatar($actor, $id);
        abort_unless($a->status === 'ready' || ($bound && $a->status === 'archived'), 409, '人物模板已归档、撤权或尚未准备。');
        $v = MarketingAvatarVersion::where('avatar_id', $id)->where('version', $version)->firstOrFail();
        foreach ($v->references as $r) {
            $this->path($r);
        }

return $v;
    }

    public function listAvailable(User $actor): array
    {
        $out = [];
        foreach ($this->all($actor)->where('status', 'ready') as $a) {
            try {
                $v = $this->resolveVersion($actor, $a->id, $a->current_version);
                $out[] = ['id' => 'avatar:'.$a->id, 'avatar_id' => $a->id, 'name' => $a->name, 'kind' => $v->kind, 'version' => $v->version, 'look' => $v->look, 'source' => 'avatar_library', 'preview_url' => route('marketing.avatars.image', [$a->id, $v->version, 'identity'])];
            } catch (HttpExceptionInterface $e) {
                if ($e->getStatusCode() !== 409) {
                    throw $e;
                }
            }
        }

return $out;
    }

    public function state(User $actor,string $id,string $state): void
    {
        abort_unless(in_array($state,['archived', 'revoked', 'ready'],true),422);
        $a = $this->avatar($actor,$id,true);
        abort_unless($a->status !== 'revoked' && ($state !== 'ready' || $a->current_version > 0),409);
        $a->update(['status' => $state]);
    }
}
