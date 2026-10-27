<?php

namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use Illuminate\Support\Facades\Storage;

/** Compiles confirmed data locally. This class never invokes AI or downloads media. */
class MarketingProductionCompiler
{
    /** Shared read-only file probes, with no layout assumptions and no provider calls. */
    public function planningMedia(MarketingProject $p,array $scene):array
    {
        $media=[];$blockers=[];
        foreach(array_unique($scene['asset_ids']) as $id){
            $a=$p->assets()->whereKey($id)->first();
            $info=$a && $a->authorized ? ($a->mime==='video/mp4'?$this->video(Storage::disk('local')->path(''),$a->path):(str_starts_with($a->mime,'image/')?$this->image(Storage::disk('local')->path(''),$a->path):null)):null;
            if($info&&$a->mime==='video/mp4'){
                try{$probe=app(MarketingMediaTools::class)->probe(Storage::disk('local')->path($a->path));$info['has_audio']=collect($probe['streams']??[])->contains(fn($s)=>($s['codec_type']??null)==='audio');}catch(\Throwable){$info=null;}
            }
            if(!$info){$blockers[]='A private material is missing, invalid, unauthorized or belongs to another project.';continue;}
            $media[]=['source'=>'private','asset_id'=>$id,'name'=>$a->name,'path'=>$a->path,'mime'=>$a->mime]+$info;
        }
        foreach($p->snapshot['images']??[] as $image){
            $path=$image['path']??'';
            if(!is_string($path)||$path===''||!str_contains($scene['material'].' '.$scene['visual'],$path))continue;
            $relative=ltrim($path,'/');$storage=str_starts_with($relative,'storage/');
            $info=$this->image($storage?Storage::disk('public')->path(''):public_path(),$storage?substr($relative,8):$relative);
            if(!$info){$blockers[]='A referenced saved catalog image is missing or invalid.';continue;}
            $media[]=['source'=>'catalog_snapshot','name'=>'Catalog image','path'=>$path]+$info;
        }
        return ['media'=>$media,'blockers'=>$blockers];
    }

    public function compile(MarketingProject $p, MarketingRevision $r): array
    {
        if(app(MarketingProductionHandoff::class)->applies($r))return app(MarketingProductionHandoff::class)->compile($p,$r);
        if(($r->context['production_recipe']??null)===MarketingToolPlanning::RECIPE||(app(MarketingToolPlanning::class)->enabled($p->id)&&($r->context['production_recipe']??null)!==MarketingNativePresenter::RECIPE))return app(MarketingToolPlanning::class)->compile($p,$r);
        if(($r->context['production_recipe']??null)===MarketingNativePresenter::RECIPE)return app(MarketingNativePresenter::class)->compile($p,$r);
        $doc = $r->document;
        $blockers = [];
        $concept = $r->context['concept'] ?? [];
        $recipe = $r->context['production_recipe'] ?? 'existing-images-voice-v1';
        $hybrid = $recipe === 'use-scenario-hybrid-v1';
        $presentation = null;
        if (! in_array($recipe, ['existing-images-voice-v1', 'use-scenario-hybrid-v1'], true)) { $blockers[] = $recipe === 'pending-tool-routing' ? '分镜已保存；需要先核实逐幕制作工具与素材方案，尚未批准制作。' : 'This production recipe is not supported.'; }
        if ($hybrid) {
            try {
                $presentation = \Illuminate\Support\Facades\Validator::make(['p' => $r->context['presentation'] ?? null], [
                    'p' => 'required|array:brand,scenes', 'p.brand' => 'required|array:name,descriptor', 'p.brand.name' => 'required|string|max:16', 'p.brand.descriptor' => 'required|string|max:60',
                    'p.scenes' => 'required|array|size:6', 'p.scenes.*' => 'required|array:layout,heading,lines,body', 'p.scenes.*.layout' => 'required|string', 'p.scenes.*.heading' => 'required|string|max:100',
                    'p.scenes.*.lines' => 'present|array|size:3', 'p.scenes.*.lines.*' => 'required|string|max:24', 'p.scenes.*.body' => 'present|string|max:160',
                ])->validate()['p'];
                if (count($doc['scenes']) !== 6 || array_column($presentation['scenes'], 'layout') !== ['need_open', 'product_intro', 'use_map', 'business_uses', 'requirements', 'cta_close']) { throw new \RuntimeException; }
            } catch (\Throwable) { $blockers[] = 'This six-scene recipe needs a valid, fixed presentation snapshot.'; }
        }
        if (($concept['ratio'] ?? '') !== '9:16') {
            $blockers[] = 'This first production mode supports vertical 9:16 only. Keep this storyboard saved for a compatible production mode.';
        }
        if (! in_array(strtolower(trim($p->brief['language'] ?? '')), ['english', 'en', 'en-my', 'en-us', 'en-gb'], true)) {
            $blockers[] = 'This first production mode has only verified English voiceover. This language needs a separate production check.';
        }
        if (($doc['duration'] ?? 0) > 60 || ($doc['duration'] ?? 0) < 1) {
            $blockers[] = 'This production mode supports storyboards up to 60 seconds. Your confirmed version has not been shortened.';
        }
        $assets = $p->assets()->get()->keyBy('id');
        $scenes = [];
        foreach ($doc['scenes'] as $i => $scene) {
            $images = [];
            $videos = [];
            // P1 explicitly describes composited English text cards as future "generation".
            // Those are local overlays; other requested generation/footage needs its own recipe.
            $materialNeeds = preg_replace('/Planned future generation:\s*English [^.]*text card[^.]*\.?/iu', '', $scene['material']);
            if (! $hybrid && preg_match('/\b(?:AI|generate\w*|generation|footage|video|b-roll|filming|recording|stock)\b|生成|拍摄|视频|影片|素材库/iu', $materialNeeds)) {
                $blockers[] = 'Scene '.($i + 1).': the requested generated footage or additional material needs a different production recipe. It will not be silently replaced with a still image.';
            }
            foreach (array_unique($scene['asset_ids']) as $id) {
                $a = $assets->get($id);
                if ($hybrid && $a && $a->authorized && $a->mime === 'video/mp4') {
                    $media = $this->video(Storage::disk('local')->path(''), $a->path);
                    if ($media) { $videos[] = ['source' => 'private', 'asset_id' => $a->id, 'name' => $a->name, 'path' => $a->path] + $media; }
                    else { $blockers[] = 'Scene '.($i + 1).': the saved clip is missing or is not a supported local video.'; }
                    continue;
                }
                $media = $a && $a->authorized && str_starts_with($a->mime, 'image/')
                    ? $this->image(Storage::disk('local')->path(''), $a->path) : null;
                if (! $media) {
                    $blockers[] = 'Scene '.($i + 1).': a referenced private image is missing, not authorized, invalid, or not supported by this image-based mode.';
                    continue;
                }
                $images[] = ['source' => 'private', 'asset_id' => $a->id, 'name' => $a->name, 'path' => $a->path] + $media;
            }
            // Catalog references are matched only to the saved source and explicit scene text.
            $sceneText = $scene['material'].' '.$scene['visual'];
            foreach ($p->snapshot['images'] ?? [] as $image) {
                $path = $image['path'] ?? '';
                if (! is_string($path) || $path === '' || ! str_contains($sceneText, $path)) {
                    continue;
                }
                $relative = ltrim($path, '/');
                $storage = str_starts_with($relative, 'storage/');
                $media = $this->image($storage ? Storage::disk('public')->path('') : public_path(), $storage ? substr($relative, 8) : $relative);
                if (! $media) {
                    $blockers[] = 'Scene '.($i + 1).': the referenced catalog image is unavailable or not a supported local image.';
                    continue;
                }
                $images[] = ['source' => 'catalog_snapshot', 'name' => 'Catalog image', 'path' => $path] + $media;
            }
            $textOnly = preg_match('/\btext-only\b|纯文字/u', $scene['material']) === 1;
            if (! $images && ! $videos && ! $textOnly) {
                $blockers[] = 'Scene '.($i + 1).': no exact usable image reference was found. Update the storyboard to reference an authorized project image, or use a later production mode for the requested footage.';
            }
            if ($hybrid && (($i === 0 && count($videos) !== 1) || ($i > 0 && count($videos)) || ($i !== 0 && count($images) > 1))) { $blockers[] = 'This recipe requires one opening clip and at most one image per later scene.'; }
            if ($hybrid && in_array($i, [1, 4], true) && count($images) !== 1) { $blockers[] = 'Scene '.($i + 1).': the product image required by this layout is unavailable.'; }
            if ($hybrid && ! in_array($i, [1, 4], true) && count($images)) { $blockers[] = 'Scene '.($i + 1).': this layout cannot render the referenced image. Keep the exact approved material binding.'; }
            $scenes[] = ['number' => $i + 1, 'mode' => $videos ? 'existing_video' : ($textOnly && ! $images ? 'text_card' : 'existing_images'), 'visual' => $scene['visual'], 'material' => $scene['material'], 'images' => $images, 'videos' => $videos, 'voiceover' => $scene['voiceover'] ?? '', 'subtitle' => $scene['subtitle'] ?? '', 'planned_seconds' => $scene['duration']];
            if ($hybrid) {
                $scenes[array_key_last($scenes)]['viewer_copy']=['text'=>$scene['subtitle']??'', 'source'=>'document.scenes.'.$i.'.subtitle'];
            }
        }
        $spoken = implode("\n\n", array_values(array_filter(array_column($scenes, 'voiceover'), fn ($s) => trim($s) !== '')));
        if (mb_strlen($spoken) > 1000) {
            $blockers[] = 'The voiceover exceeds this internal test profile of 1000 characters. A new production budget profile is required.';
        }
        if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $spoken)) {
            $blockers[] = 'The voiceover includes a language outside the verified English production profile.';
        }
        $rawCap = (string) config('marketing.production.audio_cap_usd', '');
        $validCap = preg_match('/^(?:[0-4](?:\.\d{1,2})?|5(?:\.0{1,2})?)$/D', $rawCap) === 1 && (float) $rawCap > 0;
        $cap = $spoken === '' ? 0 : ($validCap ? (int) round((float) $rawCap * 1000000) : null);
        if ($hybrid) { $cap = 0; }
        $creditAuthorization=$hybrid ? app(MarketingAudioCreditAuthorization::class)->profile($p,$r,$spoken) : null;
        if ($spoken !== '' && ! $validCap && ! $hybrid) {
            $blockers[] = 'An administrator must configure the internal test spending limit before budget confirmation. No supplier price has been assumed.';
        }

        $change=$r->context['production_change']??null;
        if($change && array_column($change['planned_scenes'],'voiceover')===array_column($scenes,'voiceover')){
            foreach($scenes as $i=>&$s){
                $base=$change['scene_timings'][$i]['frames']??null;
                if(is_int($base))$s['minimum_frames']=max(1,$base+($s['planned_seconds']-$change['planned_scenes'][$i]['planned_seconds'])*24);
            }unset($s);
        }
        $manifest = ['schema_version' => 1, 'copy_contract' => $hybrid ? 'storyboard-viewer-copy-v1' : null, 'recipe' => $recipe, 'presentation' => $presentation, 'ratio' => '9:16', 'requested_ratio' => $concept['ratio'] ?? null, 'planned_seconds' => $doc['duration'], 'summary' => $doc['summary'], 'cta' => $doc['cta'], 'scenes' => $scenes,
            'audio' => ['text' => $spoken, 'characters' => mb_strlen($spoken), 'provider' => 'elevenlabs', 'model' => 'eleven_flash_v2_5', 'voice' => '21m00Tcm4TlvDq8ikWAM', 'requests' => $spoken === '' ? 0 : 1, 'reuse_only' => $hybrid&&!$creditAuthorization, 'credit_authorization'=>$creditAuthorization, 'timing' => 'Measure actual audio and align captions before composition; planned duration is not final duration.'],
            'budget' => ['currency' => $creditAuthorization?'ElevenLabs credits':'USD', 'cap_credits'=>$creditAuthorization['max_credits']??null, 'cap_microusd' => $cap, 'supplier_estimate_usd' => null, 'kind' => $creditAuthorization?'existing_credit_authorization':'internal_test_spending_limit', 'ai_video_requests' => 0, 'composition_external_api_usd' => 0, 'billing_note' => 'Spending authorization only, not a supplier quotation or commercial price. Actual provider billing must be recorded during production. Local operating costs are not priced here.'],
            'blockers' => array_values(array_unique($blockers)), 'execution' => 'not_dispatched', 'publication_authorized' => false];
        if($change){
            // A video-change draft never inherits a previous paid production grant.
            $manifest['audio']['reuse_only']=true;$manifest['audio']['credit_authorization']=null;
            $manifest['budget']=array_replace($manifest['budget'],['currency'=>'USD','cap_credits'=>null,'cap_microusd'=>0,'kind'=>'saved_assets_only']);
            $manifest['change_check']=app(MarketingVideoChange::class)->productionCheck($p,$manifest);
            if(!$manifest['change_check']['voice_reusable'])$manifest['blockers'][]=$manifest['change_check']['reason'];
        }
        return $manifest;
    }

    private function video(string $root, string $relative): ?array
    {
        if ($relative === '' || preg_match('~(^[/\\\\]|^[A-Za-z]:|://|\.\.|[\x00-\x1f])~', $relative)) { return null; }
        $base = realpath($root);
        $path = $base ? realpath($base.DIRECTORY_SEPARATOR.$relative) : false;
        if (! $base || ! $path || ! str_starts_with(strtolower($path), strtolower($base.DIRECTORY_SEPARATOR)) || ! is_file($path) || filesize($path) < 16 || filesize($path) > 30000000) { return null; }
        try {
            $tools = app(MarketingMediaTools::class);
            $probe = $tools->probe($path);
            $v = collect($probe['streams'] ?? [])->where('codec_type', 'video');
            $s = $v->first(); $duration = (float) ($probe['format']['duration'] ?? 0);
            if ($v->count() !== 1 || ($s['codec_name'] ?? '') !== 'h264' || $duration <= 0 || $duration > 15 || ($s['width'] ?? 0) < 1 || $s['width'] > 1920 || ($s['height'] ?? 0) < 1 || $s['height'] > 1920 || (int) ($s['nb_read_frames'] ?? 0) < 1 || (isset($s['nb_frames']) && ctype_digit((string) $s['nb_frames']) && $s['nb_frames'] !== $s['nb_read_frames'])) { return null; }
            $tools->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-xerror', '-i', $path, '-map', '0:v:0', '-an', '-c:v', 'rawvideo', '-f', 'null', '-'], 60, true);
            return ['sha256' => hash_file('sha256', $path), 'bytes' => filesize($path), 'mime' => 'video/mp4', 'width' => $s['width'], 'height' => $s['height'], 'duration_seconds' => $duration];
        } catch (\Throwable) { return null; }
    }

    private function image(string $root, string $relative): ?array
    {
        if ($relative === '' || preg_match('~(^[/\\\\]|^[A-Za-z]:|://|\.\.|[\x00-\x1f])~', $relative)) {
            return null;
        }
        $base = realpath($root);
        $path = $base ? realpath($base.DIRECTORY_SEPARATOR.$relative) : false;
        if (! $base || ! $path || ! str_starts_with(strtolower($path), strtolower($base.DIRECTORY_SEPARATOR)) || ! is_file($path)) {
            return null;
        }
        $bytes = filesize($path);
        if (! $bytes || $bytes > 10 * 1024 * 1024) {
            return null;
        }
        $size = @getimagesize($path);
        if (! $size || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $size[0] > 8000 || $size[1] > 8000 || $size[0] * $size[1] > 12000000 || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        // Header-only probes accept truncated files. Bound memory before checking
        // that a real decoder can read the image, not just its dimensions.
        $decoded = @imagecreatefromstring(file_get_contents($path));
        if ($decoded === false) {
            return null;
        }
        imagedestroy($decoded);

        return ['sha256' => hash_file('sha256', $path), 'bytes' => $bytes, 'mime' => $size['mime'], 'width' => $size[0], 'height' => $size[1]];
    }

    public function sourcePath(array $image): string
    {
        $relative = $image['path'];
        if ($image['source'] === 'private') {
            $root = Storage::disk('local')->path('');
        } elseif ($image['source'] === 'catalog_snapshot') {
            $relative = ltrim($relative, '/');
            $storage = str_starts_with($relative, 'storage/');
            $root = $storage ? Storage::disk('public')->path('') : public_path();
            $relative = $storage ? substr($relative, 8) : $relative;
        } else {
            throw new MarketingProductionFailure('material_changed', 'blocked');
        }
        $check = ($image['mime'] ?? '') === 'video/mp4' ? $this->video($root, $relative) : $this->image($root, $relative);
        if (! $check || ! hash_equals($image['sha256'], $check['sha256'])) {
            throw new MarketingProductionFailure('material_changed', 'blocked');
        }

        return realpath($root.DIRECTORY_SEPARATOR.$relative);
    }
}
