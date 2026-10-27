<?php

namespace App\Services\Marketing;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingVoiceAsset;
use Illuminate\Support\Facades\Storage;

class MarketingProductionWorkspace
{
    public function stage(MarketingProduction $task, MarketingProductionPlan $plan): string
    {
        $disk = Storage::disk('local');
        $base = 'marketing-productions/'.$task->id;
        $disk->makeDirectory($base.'/public');
        $manifest = $plan->manifest;
        $receipts=[];
        foreach ($manifest['scenes'] as &$scene) {
            foreach ($scene['videos'] ?? [] as $index => $video) {
                if(isset($video['node_ref'])){
                    if(($manifest['execution_profile']??null)!==MarketingHybridProduction::PROFILE)throw new MarketingProductionFailure('hybrid_media_unbound','blocked');
                    $node=\App\Models\MarketingProductionStep::where('production_id',$task->id)->where('step_key',$video['node_ref'])->where('kind','generate_video')->where('status','completed')->first();
                    $video=$node?->result['media']??null;$expected=$base.'/generated-shot.mp4';$source=$disk->path($expected);
                    if(!$node||!is_array($video)||($video['path']??null)!==$expected||!is_file($source)||realpath($source)!==realpath($disk->path('')).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$expected)||!hash_equals($node->output_sha256,hash_file('sha256',$source))||!hash_equals($node->output_sha256,$video['sha256']))throw new MarketingProductionFailure('hybrid_media_changed','blocked');
                    $receipts[]=['node_key'=>$node->step_key,'status'=>'completed','sha256'=>$node->output_sha256];
                    $scene['videos'][$index]=$video;
                }else $source = app(MarketingProductionCompiler::class)->sourcePath($video);
                $name = $video['sha256'].'.mp4';
                $target = $disk->path($base.'/public/'.$name);
                if (! copy($source, $target) || ! hash_equals($video['sha256'], hash_file('sha256', $target))) { throw new MarketingProductionFailure('material_changed', 'blocked'); }
                $scene['videos'][$index]['file'] = $name;
            }
            foreach ($scene['images'] as &$image) {
                $source = app(MarketingProductionCompiler::class)->sourcePath($image);
                $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$image['mime']];
                $name = $image['sha256'].'.'.$extension;
                $target = $disk->path($base.'/public/'.$name);
                if (! copy($source, $target) || ! hash_equals($image['sha256'], hash_file('sha256', $target))) {
                    throw new MarketingProductionFailure('material_changed', 'blocked');
                }
                $image['file'] = $name;
            }
            unset($image);
        }
        unset($scene);
        if (($manifest['recipe']??null)==='capability-plan-v1') {
            if(($manifest['execution_profile']??null)===MarketingHybridProduction::PROFILE){if(count($receipts)!==1)throw new MarketingProductionFailure('hybrid_receipt_invalid','blocked');$manifest['generation_receipts']=$receipts;}
            $manifest['scenes']=array_map(function($scene){$s=\Illuminate\Support\Arr::only($scene,['number','scene_id','mode','images','videos','voiceover','subtitle','planned_seconds','minimum_frames','viewer_copy']);foreach(['images','videos'] as $key)$s[$key]=array_map(fn($file)=>\Illuminate\Support\Arr::only($file,['file','mime','width','height','duration_seconds','has_audio']),$s[$key]);return $s;},$manifest['scenes']);
            $manifest=\Illuminate\Support\Arr::only($manifest,['recipe','execution_profile','ratio','scenes','presentation','cta','audio','budget','generation_receipts']);
            $manifest['audio']=\Illuminate\Support\Arr::only($manifest['audio'],['requests','new_requests','reuse_only']);
            $manifest['budget']=\Illuminate\Support\Arr::only($manifest['budget'],['cap_microusd','ai_video_requests']);
        } elseif (($manifest['recipe']??null)==='use-scenario-hybrid-v1') {
            // Keep director notes in the immutable plan, never in renderer inputs.
            $manifest['scenes']=array_map(fn($scene)=>\Illuminate\Support\Arr::only($scene,['number','mode','images','videos','voiceover','subtitle','planned_seconds','minimum_frames','viewer_copy']),$manifest['scenes']);
            $manifest['presentation']=['brand'=>$manifest['presentation']['brand'],'scenes'=>array_map(fn($scene)=>['layout'=>$scene['layout']],$manifest['presentation']['scenes'])];
            unset($manifest['summary']);
        }
        $input = ['manifest' => $manifest, 'title' => $plan->snapshot['source']['title'], 'revision' => $plan->snapshot['revision_number'], 'qa' => str_starts_with($plan->snapshot['brief']['facts'] ?? '', 'LOCAL QA')];
        if (! $disk->put($base.'/input.json', json_encode($input, JSON_THROW_ON_ERROR))) {
            throw new MarketingProductionFailure('material_storage_failed');
        }

        return $disk->path($base);
    }

    public function stageVoice(string $directory, MarketingVoiceAsset $voice): void
    {
        $disk = Storage::disk('local');
        app(MarketingVoiceLibrary::class)->validateCached($voice);
        if (! copy($disk->path($voice->audio_path), $directory.'/public/voice.mp3') || ! copy($disk->path($voice->alignment_path), $directory.'/alignment.json') || ! hash_equals($voice->sha256, hash_file('sha256', $directory.'/public/voice.mp3'))) {
            throw new MarketingProductionFailure('voice_cache_invalid', 'blocked');
        }
    }
}
