<?php

namespace App\Services\Marketing;

use App\Models\MarketingAsset;
use App\Models\MarketingStoryboardPresenter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class MarketingAvatarBinding
{
    public function bind(string $project, int $owner, string $avatar, int $version): array
    {
        app(MarketingStoryboardWorkspace::class)->project($project, $owner, true);
        $lib = app(MarketingAvatarLibrary::class);
        $v = $lib->resolveVersion(User::findOrFail($owner), $avatar, $version);
        $request = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'avatar-binding:'.$project.':'.$v->id);
        $old = MarketingStoryboardPresenter::where('project_id', $project)->where('request_id', $request)->first();
        if ($old) {
            $this->assertUsable($project, $owner, $old->avatar_binding);

            return $this->item($old);
        }

        return DB::transaction(function () use ($lib, $v, $project, $owner, $request, $avatar, $version) {
            $refs = [];
            foreach ($v->references as $role => $ref) {
                $bytes = file_get_contents($lib->path($ref));
                $path = 'marketing/'.$project.'/avatar-references/'.$ref['sha256'].'.'.match ($ref['mime']) {
                    'image/png' => 'png','image/jpeg' => 'jpg','image/webp' => 'webp',default => abort(422)
                };
                Storage::disk('local')->put($path, $bytes);
                $asset = MarketingAsset::firstOrCreate(['project_id' => $project, 'path' => $path], ['name' => $v->name.' '.$role, 'mime' => $ref['mime'], 'size' => strlen($bytes), 'authorized' => true]);
                $refs[$role] = app(MarketingStoryboardUpload::class)->reference($project, $asset->id);
            }
            $binding = ['avatar_id' => $avatar, 'avatar_version' => $version, 'look_version' => $version, 'version_id' => $v->id, 'identity_digest' => $v->identity_digest, 'look' => $v->look, 'project_references' => $refs, 'source_type' => 'avatar_library'];
            $row = MarketingStoryboardPresenter::firstOrCreate(['project_id' => $project, 'request_id' => $request], ['owner_id' => $owner, 'request_digest' => hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR)), 'name' => $v->name, 'kind' => $v->kind, 'version' => 1, 'asset_id' => $refs['identity']['asset_id'], 'reference' => $refs['identity'], 'avatar_binding' => $binding]);

            return $this->item($row);
        });
    }

    public function assertUsable(string $project, int $owner, array $binding): void
    {
        $v = app(MarketingAvatarLibrary::class)->resolveVersion(User::findOrFail($owner), $binding['avatar_id'], $binding['avatar_version'], true);
        abort_unless($v->id === $binding['version_id'] && hash_equals($v->identity_digest, $binding['identity_digest']), 409, '固定人物版本已失效。');
        foreach ($binding['project_references'] as $role => $ref) {
            $fresh = app(MarketingStoryboardUpload::class)->reference($project, $ref['asset_id']);
            abort_unless(hash_equals($fresh['sha256'], $ref['sha256']) && hash_equals($v->references[$role]['sha256'], $ref['sha256']), 409, '项目人物参考已改变。');
        }
    }

    public function item(MarketingStoryboardPresenter $row): array
    {
        return ['id' => $row->id, 'name' => $row->name, 'kind' => $row->kind, 'version' => $row->version, 'reference' => $row->reference, 'preview_url' => $row->reference['local_preview'], 'source' => 'avatar_library', 'avatar_binding' => $row->avatar_binding];
    }
}
