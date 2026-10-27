<?php

namespace App\Services\Marketing;

use Illuminate\Support\Str;

class MarketingStoryboardChanges
{
    public function change(string $project, int $owner, string $request, int $storyVersion, array $command): array
    {
        $workspace = app(MarketingStoryboardWorkspace::class);
        $p = $workspace->project($project, $owner);
        $before = $workspace->snapshot($project, $owner);
        $rawCommand = $command;
        $scope=$command['update_scope']??null;
        $oldReceipt=\App\Models\MarketingStoryboardCommand::where('project_id',$project)->where('request_id',$request)->first();if($oldReceipt){abort_unless(hash_equals($oldReceipt->digest,hash('sha256',json_encode([$storyVersion,$rawCommand],JSON_THROW_ON_ERROR))),409,'请求内容已改变。');return ['change_id'=>$request,'snapshot'=>$oldReceipt->result,'affected_scene_ids'=>[],'needs_authorization'=>!($oldReceipt->result['readiness']??false)];}
        if(($command['mode']??'')==='image_only'){
            $scene=collect($before['scenes'])->firstWhere('scene_id',$command['scene_id']);
            abort_unless($scene,422,'当前分镜不存在。');
            // The server-owned authored revision is the input; readout/QA metadata is not authoring.
            $command['content']=\App\Models\MarketingSceneRevision::findOrFail($scene['revision_id'])->content;
            foreach($command['content']['frames'] as &$frame){
                $frame=array_intersect_key($frame,array_flip(['required','instruction','state','person_presence']));
            }unset($frame);
        }

            abort_unless($before['story_version'] === $storyVersion, 409, '故事已更新，请刷新。');
            if (($command['mode'] ?? 'manual') === 'ai') {
                $facts = [];
                if (($p->brief['facts_confirmed'] ?? false) && ($p->brief['source_confirmed'] ?? false)) {
                    foreach (['title', 'description'] as $k) {
                        if (! empty($p->snapshot[$k])) {
                            $facts['snapshot.'.$k] = (string) $p->snapshot[$k];
                        }
                    }if (is_string($p->brief['facts'] ?? null)) {
                        $facts['brief.facts'] = $p->brief['facts'];
                    }
                }
                foreach ($before['scenes'] as $known) { foreach (['voiceover','subtitle'] as $field) { if (($known[$field]??'')!=='') $facts['scene.'.$known['scene_id'].'.'.$field]=$known[$field]; } }
                $before['product_reference_catalog']=array_map(fn($i,$url)=>['index'=>$i,'source'=>$url,'sha256'=>($path=app(MarketingMaterialPackage::class)->catalogPath($p,$i))?hash_file('sha256',$path):null],array_keys($p->snapshot['images']??[]),array_values($p->snapshot['images']??[]));
                if (isset($command['requested_seconds'])) abort_unless(is_int($command['requested_seconds']) && $command['requested_seconds'] >= 3 && $command['requested_seconds'] <= 15, 422, '预计时长应为3至15秒。');
                if(($command['timing_resolution']??'')==='shorten'&&isset($command['confirmed_voiceover']))$facts['employee.confirmed_voiceover']=$command['confirmed_voiceover'];
                $intent = ['text' => $command['intent'] ?? '', 'kind' => ($command['rewrite'] ?? false) ? 'rewrite' : 'adjust', 'requested_seconds'=>$command['requested_seconds']??null, 'operation'=>$command['type']??'', 'scene_id'=>$command['scene_id']??null, 'before_id'=>$command['before_id']??null, 'after_id'=>$command['after_id']??null, 'project_id'=>$project,'authorization_id'=>$command['authorization_id']??''];
                $ordered=$before['scenes'];
                $index=isset($command['scene_id'])?array_search($command['scene_id'],array_column($ordered,'scene_id'),true):false;
                if(($command['type']??'')==='insert'){
                    $anchor=$command['before_id']??$command['after_id']??null;
                    $at=$anchor!==null?array_search($anchor,array_column($ordered,'scene_id'),true):count($ordered);
                    abort_if($at===false,422,'分镜插入位置已变化，请刷新。');
                    $index=$at+(isset($command['after_id'])?1:0);
                }
                $intent['adjacent_scenes']=['previous'=>$index!==false?($ordered[$index-1]??null):null,'next'=>$index!==false?($ordered[$index+(($command['type']??'')==='insert'?0:1)]??null):null];
                $intent['production_tier']=$before['profile']['production_tier']??'ordinary';
                $intent['video_capabilities']=app(MarketingVideoRoutes::class)->catalog();
                $intent['speech_estimate']=['characters_per_second'=>4,'latin_words_per_second'=>2.5,'actual_audio_timing'=>'step4'];
                if(!empty($command['refine_manual'])){
                    $authored=app(MarketingStoryboardContract::class)->content($command['content']??[]);
                    $intent['authored_content']=array_intersect_key($authored,array_flip(['seconds','message','visual','voiceover','subtitle']));
                    $intent['text']='按员工填写的导演稿补齐场景、人物、商品、道具、镜头、动作节拍和首尾补充帧设计。保留员工填写的主要信息、口播、字幕及目标时长。';
                }
                abort_if(! is_string($intent['text']) || mb_strlen($intent['text']) < 1 || mb_strlen($intent['text']) > 4000, 422, '请描述修改想法。');
                $media = [];
                if (! empty($command['asset_id'])) {
                    $media[] = app(MarketingStoryboardUpload::class)->reference($project, $command['asset_id']);
                }$draft = app(MarketingStoryboardSemanticClient::class)->propose($facts, $before, $intent, $media);
                // Step 3 is employee-directed creative production; reality questions are not an approval gate.
                if(isset($authored)){
                    abort_unless(count($draft['scenes'])===1,422,'自己填写的单段导演稿不能自动拆分。');
                    foreach(['seconds','message','voiceover','subtitle'] as $field)$draft['scenes'][0][$field]=$authored[$field];
                }
                if(isset($command['requested_seconds'])&&!($command['rewrite']??false)){
                    abort_unless(count($draft['scenes'])===1,422,'指定单段时长时不能自动拆分。');
                    $draft['scenes'][0]['seconds']=$command['requested_seconds'];
                    $beats=$draft['scenes'][0]['director']['beats']??[];
                    $end=max(array_column($beats,'end')?:[1]);
                    foreach($beats as &$beat){$beat['start']=round($beat['start']/$end*$command['requested_seconds'],3);$beat['end']=round($beat['end']/$end*$command['requested_seconds'],3);}unset($beat);
                    $draft['scenes'][0]['director']['beats']=$beats;
                    if(($command['timing_resolution']??'')==='shorten'){
                        $draft['scenes'][0]['voiceover']=$command['confirmed_voiceover'];
                        $draft['scenes'][0]['subtitle']=$command['confirmed_voiceover'];
                        $draft['scenes'][0]['fact_refs'][]='employee.confirmed_voiceover';
                    }
                }
                if (($command['type'] ?? '') === 'insert') {
                    abort_unless(count($draft['scenes']) === 1, 422, '新增内容需要拆分，请先调整范围。');
                    $command['content'] = $draft['scenes'][0];
                } else {
                    if (count($draft['scenes']) === 1 && ! ($command['rewrite'] ?? false)) {
                        $command['type'] = 'edit';
                        $command['content'] = $draft['scenes'][0];
                    } else {
                        abort_unless($command['rewrite'] ?? false, 422, '本次表达需要拆分，请选择允许整段重写与延长时长。');
                        $command['type'] = 'replace';
                        $command['contents'] = $draft['scenes'];
                    }
                }
            }
        $snapshot=$workspace->transact($project,$owner,$request,[$storyVersion,$rawCommand],function()use($workspace,$project,$owner,$storyVersion,$command){$workspace->apply($project,$owner,(string)Str::uuid(),$storyVersion,$command);});
        $old = collect($before['scenes'])->keyBy('scene_id');
        $affected = [];
        foreach ($snapshot['scenes'] as $s) {
            if (! isset($old[$s['scene_id']]) || $old[$s['scene_id']]['revision_id'] !== $s['revision_id']) {
                $affected[] = $s['scene_id'];
            }
        }

        return ['change_id' => $request, 'snapshot' => $snapshot, 'affected_scene_ids' => $affected, 'needs_authorization' => ! $snapshot['readiness']]+($scope?['update_scope'=>$scope]:[]);
    }
}
