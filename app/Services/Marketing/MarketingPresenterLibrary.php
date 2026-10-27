<?php

namespace App\Services\Marketing;

use App\Models\MarketingStoryboardPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingPresenterLibrary
{
    public function register(string $project, int $owner, string $request, string $name, string $kind, string $asset, bool $consent): array
    {
        abort_unless(Str::isUuid($request) && $consent && in_array($kind, ['synthetic', 'real'], true) && mb_strlen(trim($name)) > 0 && mb_strlen($name) <= 80, 422, '请填写模板名称并确认有权使用人物参考。');

        return DB::transaction(function () use ($project, $owner, $request, $name, $kind, $asset) {
            app(MarketingStoryboardWorkspace::class)->project($project, $owner, true);
            $ref = app(MarketingStoryboardUpload::class)->reference($project, $asset);
            abort_unless($ref['kind'] === 'image', 422, '人物模板需要清晰图片。');
            $digest = hash('sha256', json_encode([$name, $kind, $ref['sha256']], JSON_THROW_ON_ERROR));
            $old = MarketingStoryboardPresenter::where('project_id', $project)->where('request_id', $request)->first();
            if ($old) {
                abort_unless(hash_equals($old->request_digest, $digest), 409, '模板请求已绑定其他内容。');

                return $this->item($old);
            }$row = MarketingStoryboardPresenter::create(['project_id' => $project, 'owner_id' => $owner, 'request_id' => $request, 'request_digest' => $digest, 'name' => trim($name), 'kind' => $kind, 'asset_id' => $asset, 'reference' => $ref]);

            return $this->item($row);
        });
    }

    private function item(MarketingStoryboardPresenter $row): array
    {
        $item=$row->avatar_binding?app(MarketingAvatarBinding::class)->item($row):['id' => $row->id, 'name' => $row->name, 'kind' => $row->kind, 'version' => $row->version, 'reference' => $row->reference, 'preview_url' => $row->reference['local_preview'], 'source' => 'authorized_project_reference'];
        $fresh=app(MarketingStoryboardUpload::class)->reference($row->project_id,$row->asset_id);
        abort_unless(hash_equals($row->reference['sha256'],$fresh['sha256']),409,'人物参考已改变。');
        $item['reference']['local_preview']=$fresh['local_preview'];$item['preview_url']=$fresh['local_preview'];
        return $item;
    }

    public function options(?string $project = null, ?int $owner = null): array
    {
        $out = [];
        if ($project && $owner) {
            app(MarketingStoryboardWorkspace::class)->project($project, $owner);
            foreach (MarketingStoryboardPresenter::where('project_id', $project)->where('owner_id', $owner)->get() as $row) {
                try {
                    if ($row->avatar_binding) app(MarketingAvatarBinding::class)->assertUsable($project,$owner,$row->avatar_binding);
                    $ref = app(MarketingStoryboardUpload::class)->reference($project, $row->asset_id);
                    if (hash_equals($ref['sha256'], $row->reference['sha256'])) {
                        $out[] = $this->item($row);
                    }
                } catch (\Throwable) {
                }
            }
        }
        if ($owner) foreach(app(MarketingAvatarLibrary::class)->listAvailable(\App\Models\User::findOrFail($owner)) as $available){
            if(!collect($out)->contains(fn($existing)=>($existing['avatar_binding']['avatar_id']??null)===$available['avatar_id']&&($existing['avatar_binding']['avatar_version']??null)===$available['version']))$out[]=$available;
        }
        foreach (config('marketing.storyboard.presenters', []) as $p) {
            if (isset($p['id'],$p['version'],$p['path'],$p['sha256']) && Storage::disk('local')->exists($p['path']) && hash_equals($p['sha256'], hash_file('sha256', Storage::disk('local')->path($p['path'])))) {
                $out[] = $p;
            }
        }

return $out;
    }

    public function resolve(string $project, int $owner, string $id, int $version): array
    {
        $row = collect($this->options($project, $owner))->first(fn ($r) => $r['id'] === $id && $r['version'] === $version);
        abort_unless($row,422,'人物模板不存在或参考已改变。');

        return $row;
    }
}
