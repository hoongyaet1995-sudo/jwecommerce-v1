<?php

namespace App\Services\Marketing;

use App\Models\MarketingLibraryAsset;
use App\Models\MarketingPost;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingVideoFilm;

/** Display-only selection; this never grants publication or production approval. */
class MarketingMediaSelection
{
    public function project(string $id): void
    {
        $p = MarketingProject::find($id);
        if (! $p) {
            return;
        }$map = [];
        $walk = function (array $d, bool $active = false) use (&$walk, &$map) {
            if (is_string($d['asset_id'] ?? null)) {
                if (in_array($d['status'] ?? '', ['rejected', 'declined'], true)) {
                    $map[$d['asset_id']] = 'not-selected';
                } elseif ($active || in_array($d['status'] ?? '', ['approved', 'adopted'], true)) {
                    $map[$d['asset_id']] = 'selected';
                }
            }
            foreach ($d as $key => $v) {
                if (is_array($v)) {
                    $walk($v, $active || $key === 'asset');
                }
            }
        };
        $walk(MarketingRevision::find($p->current_revision_id)?->document ?? []);
        foreach (MarketingSceneRevision::whereIn('id', MarketingScene::where('project_id', $id)->select('current_revision_id'))->get() as $r) {
            $walk($r->content);
        }
        MarketingLibraryAsset::where('project_id', $id)->where('source_type', 'marketing_assets')->update(['selection' => 'not-reviewed']);
        foreach ($map as $asset => $status) {
            MarketingLibraryAsset::where('project_id', $id)->where('source_id', $asset)->update(['selection' => $status]);
        }
        foreach (MarketingVideoFilm::where('project_id', $id)->get() as $film) {
            if ($film->approved_at) {
                MarketingLibraryAsset::where('source_type', 'marketing_video_films')->where('source_id', $film->id)->update(['selection' => 'selected']);
            }foreach ($film->input['scenes'] ?? [] as $scene) {
                if (! empty($scene['clip_id'])) {
                    MarketingLibraryAsset::where('source_type', 'marketing_video_clips')->where('source_id', $scene['clip_id'])->update(['selection' => 'selected']);
                }
            }
        }
    }

    public function post(MarketingPost $post): void
    {
        $owner = $post->user_id;
        $keys = [];
        MarketingPost::where('user_id', $owner)->select(['id', 'document'])->chunkById(100, function ($rows) use (&$keys) {
            foreach ($rows as $row) {
                $d = $row->document;
                if (! empty($d['deleted_at'])) {
                    continue;
                }$keys = array_merge($keys, $d['assets'] ?? (! empty($d['asset']) ? [$d['asset']] : []));
                foreach ($d['media_copies'] ?? [] as $set) {
                    $keys = array_merge($keys, $set);
                }
            }
        });
        $ids = [];
        foreach (array_unique($keys) as $key) {
            if (str_starts_with($key, 'upload:')) {
                $ids[] = substr($key, 7);
            }
        }
        MarketingLibraryAsset::where('owner_id', $owner)->where('source_type', 'marketing_post_media')->update(['selection' => 'not-reviewed']);
        if ($ids) {
            MarketingLibraryAsset::where('owner_id', $owner)->where('source_type','marketing_post_media')->whereIn('source_id',$ids)->update(['selection' => 'selected']);
        }
    }
}
