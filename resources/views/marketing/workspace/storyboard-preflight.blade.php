<link rel="stylesheet" href="{{ asset('marketing-studio/production-tiers.css') }}?v={{ filemtime(public_path('marketing-studio/production-tiers.css')) }}">
@php($storyPlan=$storyboardPreflight['plan'])
@php($storyRun=$storyboardPreflight['production']??null)
@php($completedStoryRun=$storyRun?->status==='completed')
@php($scopeScene=$storyPlan?->manifest['scope']['scene_ids'][0]??'')
@php($settingScenes=$completedStoryRun?($storyPlan?->snapshot['scene_map']??$storyboardPreflight['snapshot']['scenes']):$storyboardPreflight['snapshot']['scenes'])
@php($quoteScenes=$scopeScene?array_values(array_filter($settingScenes,fn($s)=>$s['scene_id']===$scopeScene)):$settingScenes)
@if($revision?->context['offline_acceptance']??false)<div class="alert alert-info">本地假响应验收项目 · 示例费率，不代表供应商报价，不授权真实调用。</div>@endif
@php($tier=$storyboardPreflight['snapshot']['profile']['production_tier']??'ordinary')
<div class="ws-card py-3 mb-3"><strong>当前工作稿 · 分镜 v{{ $storyboardPreflight['snapshot']['story_version'] }}</strong><span class="ws-badge ms-2">{{ $storyboardPreflight['handoff_current']?'已交接当前版本':'尚未交接' }}</span><p class="ws-note mb-0">新版交接后重新核价确认；历史批次与旧成片保留，返回修改不会自动生成。</p></div>
@if(!$storyboardPreflight['handoff_current'])<div class="alert alert-warning">当前工作稿尚未交接到制作确认。请返回 Step 3 确认当前故事与必要素材。@foreach($storyboardPreflight['snapshot']['blockers']??[] as $blocker)<div>{{ $blocker['message'] }}</div>@endforeach @if($storyboardPreflight['snapshot']['timing']['requires_attention']??false)<div>口播与时长安排仍需确认。</div>@endif</div>@endif
<div class="d-flex justify-content-between align-items-start mb-4 gap-3"><div><h2 class="ws-page-title">{{ $completedStoryRun?'本批次已完成，查看制作结果':($storyboardPreflight['handoff_current']?'方案准备好了，确认后开始制作':'当前工作稿，待交接制作') }}</h2><p class="text-secondary">{{ $completedStoryRun?'当前展示已完成的方案与结算；再次制作需要重新核价。':'模型、声音与合成都由系统安排；你确认本次范围与费用即可。' }}</p></div></div>
@if(!$storyboardPreflight['fresh'])<div class="alert alert-warning">项目来源已改变，请返回核对故事。旧预检不能继续制作。</div>@endif
<div class="pt-confirm-layout"><section class="ws-card"><div class="d-flex justify-content-between gap-2 mb-3"><h3>AI 推荐的制作方案</h3><span class="badge text-dark bg-light">{{ \App\Services\Marketing\MarketingProductionPreferences::TIERS[$tier]??'普通' }}</span></div>
<table class="pt-plan-table"><thead><tr><th>分镜</th><th>制作路线</th><th>时长</th></tr></thead><tbody>
@foreach($quoteScenes as $scene)
@php($advice=collect($storyboardPreflight['snapshot']['production_advice']??[])->firstWhere('scene_id',$scene['scene_id']))
@php($selectedRoute=$storyPlan?->manifest['configuration']['clip_routes'][$scene['scene_id']]??null)
@php($economyAdvice=$tier==='ordinary'&&($completedStoryRun||!isset($advice['execution']))?app(\App\Services\Marketing\MarketingEconomyVideoPolicy::class)->recommend($scene,$storyboardPreflight['snapshot']['profile']):null)
<tr><td><div class="pt-scene-summary">@if($scene['asset']['local_preview']??null)@if(($scene['asset']['kind']??'')==='video')<video class="pt-scene-image" src="{{ $scene['asset']['local_preview'] }}" controls preload="metadata"></video>@else<img class="pt-scene-image" src="{{ $scene['asset']['local_preview'] }}" alt="第{{ $scene['ordinal'] }}段当前素材">@endif @endif<div>第{{ $scene['ordinal'] }}段<small>{{ $scene['message'] }}</small>@include('marketing.workspace.video-setting-tags',['settingScene'=>$scene,'settingsClickable'=>!$completedStoryRun,'settingTags'=>$completedStoryRun?app(\App\Services\Marketing\MarketingVideoSettings::class)->tags($scene):($storyboardPreflight['snapshot']['video_settings']['scenes'][$scene['scene_id']]['tags']??null)])</div></div></td><td>{{ ($selectedRoute['local']??false)||isset($advice['execution'])&&!$completedStoryRun ? ((($selectedRoute['execution_policy']['route']??$advice['execution']['route']??'')==='source_locked')?'原图保真合成':((($selectedRoute['execution_policy']['route']??$advice['execution']['route']??'')==='conservative_motion')?'保守镜头运动':'制作安排待核对')) : ['single_image'=>'单图 + 导演稿','first_last_frames'=>'首尾帧 + 导演稿','reference_images'=>'参考图 + 导演稿'][$selectedRoute['mode']??$advice['mode']??'single_image'] }}<small>{{ $selectedRoute['video']['model']??$advice['model']??'模型与费用待核验' }}</small><small>{{ isset($storyPlan?->manifest['configuration']['reused_clips'][$scene['scene_id']])?'复用已完成片段，本次不重新生成或收费':($selectedRoute['reason']??$advice['reason']??'沿用已确认导演设计') }}</small>@if($economyAdvice && $economyAdvice['status']==='estimated')<small>
@if(isset($storyPlan?->manifest['configuration']['reused_clips'][$scene['scene_id']]))复用已完成片段 · 本次新增视频费用 USD 0.00
@elseif($selectedRoute)本段视频费用上限：USD {{ number_format($selectedRoute['video']['cap_microusd']/1000000,2) }}
@else
普通档位推荐：{{ $economyAdvice['model'] }} · 预计视频 USD {{ number_format($economyAdvice['public_estimate_microusd']/1000000,2) }}（公开估价，待核价）
@endif
</small>@if($selectedRoute)<small>本批次已保存模型：{{ $selectedRoute['video']['model'] }}；新模型需重新核价，不覆盖历史结果。</small>@endif @endif</td><td>{{ $scene['seconds'] }}秒</td></tr>
@endforeach</tbody></table>
<p class="ws-note mt-3">{{ $storyboardPreflight['handoff_current']?'导演稿与所需素材使用本次交接版本；原版及旧报价保留。':'这里展示当前工作稿，尚未确认交接；历史制作结果不会计入本版完成状态。' }}</p></section>
<aside class="ws-card pt-cost-panel"><h3>本次制作费用</h3>@if($completedStoryRun)<p class="ws-note">本批次已完成，已保存模型与费用属于历史结果；普通档位的新推荐需重新核价，不会覆盖旧片段。</p>@endif<p class="ws-note">{{ $scopeScene?'本次仅试生成所选分镜，不制作其余镜头，也不合成为整片。':'本次范围：整片制作。' }}</p>
@if($completedStoryRun)
@php($settled=$storyboardPreflight['settlement'])
<p class="ws-note">本批次制作已完成，原有结果保留。重新核价只用于下一次制作，不改变本批次结算。</p>
@unless(config('marketing.billing.enabled'))<p class="ws-note">实际请求：{{ $settled['posts'] }} 次 POST · 新增视频 {{ $storyPlan->manifest['budget']['video_requests']??0 }} 段 · 复用 {{ $storyPlan->manifest['budget']['reused_clips']??0 }} 段</p>@endunless
<p class="ws-note">声音路线：{{ match($storyPlan->manifest['configuration']['audio']['route']??'silent'){'native'=>'原生音画', 'independent'=>'独立配音',default=>'无口播声音'} }}。本地剪辑与字幕合成无供应商调用。</p>
<div class="ws-cost-row total"><span>本批次核算费用</span><strong>{{ $settled['known']?'USD '.number_format($settled['actual_microusd']/1000000,2):'费用待核对' }}</strong></div>
<p class="ws-note">本批次费用上限 USD {{ number_format(($settled['cap_microusd']??$storyPlan->manifest['budget']['cap_microusd']??0)/1000000,2) }}。核算金额沿用已保存 usage 与换算费率；不代表下一次报价。</p>
@elseif(!$storyboardPreflight['quote_current'])<div class="alert alert-info">{{ $storyboardPreflight['quote_issue']??($storyboardPreflight['handoff_current']?'当前版本待核价，请准备制作方案。':'当前工作稿尚未交接；旧方案不能用于本次制作。') }} 未调用收费生成；重新核价可能读取账户模型与余额。</div>
@if($storyPlan?->manifest['quote_candidate']??null)
@php($candidate=$storyPlan->manifest['quote_candidate'])
<p class="ws-note">候选视频模型：{{ $candidate['model'] }} · {{ $candidate['quality'] }} · {{ $candidate['seconds'] }}秒</p><p class="ws-note">{{ $candidate['voice_description'] }}</p><p class="ws-note">预计请求：视频 {{ $candidate['video_requests'] }} 次、上传 {{ $candidate['upload_requests'] }} 次、独立配音 {{ $candidate['voice_requests'] }} 次，共 {{ $candidate['total_posts'] }} 次 POST。不自动重试。</p><p class="ws-note">公开视频估价：{{ number_format($candidate['estimated_credits'],2) }} credits，约 USD {{ number_format($candidate['public_estimate_microusd']/1000000,2) }}；{{ $candidate['audio_price_note']??'原生音频无额外模型费用。' }} 此为估算，尚非本次实际预算上限。</p>@foreach($candidate['blockers'] as $issue)<p class="ws-note">{{ $issue }}</p>@endforeach
@endif
@else
@php($cfg=$storyPlan->manifest['configuration'])
<p class="ws-note">视频生成 · {{ $storyPlan->manifest['budget']['video_requests']??count($quoteScenes) }}个分镜 · 复用 {{ $storyPlan->manifest['budget']['reused_clips']??0 }}个已有片段</p><p class="ws-note">声音路线：{{ match($cfg['audio']['route']??'silent'){'native'=>'原生音画','independent'=>'独立配音',default=>'无口播声音'} }}。用户指定声线会保留。</p>@if(!empty($cfg['audio']['capabilities']['scoped_trial']))<p class="ws-note">@if($cfg['batch_approved']??false)各段沿用已保存台词，请求原生普通话女声；音色、台词与口型待验收。已有片段复用，新增图片逐次核对积分。@else 本次为普通话女声原生音画试生成；声音、台词和口型待实测。已上传首尾帧将复用，不会再次上传。@endif</p>@endif
<p class="ws-note">待上传 {{ $storyPlan->manifest['budget']['upload_requests']??0 }} 次 · 复用已上传 {{ $storyPlan->manifest['budget']['reused_uploads']??0 }} 张 · 配音 {{ $storyPlan->manifest['budget']['voice_requests']??0 }} 次</p><p class="ws-note">本地剪辑与字幕合成：无供应商调用</p><div class="ws-cost-row total"><span>本次费用上限</span><strong>USD {{ number_format(($storyPlan->manifest['budget']['cap_microusd']??0)/1000000,2) }}</strong></div><p class="ws-note">确认后才生成正式配音与视频；不自动追加次数、升级档位或发布。完整口播不裁剪，人物画面不承诺嘴型同步。</p>
@endif
<form data-limit="{{ number_format(($storyPlan?->manifest['budget']['cap_microusd']??0)/1000000,2,'.','') }}" data-billing="{{ config('marketing.billing.enabled')?'1':'0' }}" data-storyboard-production data-plan-scene="{{ $scopeScene }}" data-can-reprice="{{ $storyboardPreflight['handoff_current']&&$storyboardPreflight['fresh']&&(!$storyRun||$completedStoryRun)?'1':'0' }}" data-blocked="{{ !$storyboardPreflight['quote_current']||$storyRun?'1':'0' }}" data-api="{{ route('marketing.workspace.storyboard',$project->id) }}" data-version="{{ $storyboardPreflight['snapshot']['story_version'] }}" data-plan="{{ $storyPlan?->id }}" data-run="{{ $storyRun?->id }}" data-csrf="{{ csrf_token() }}">
<label class="d-block small mb-3">本次制作范围<select name="production_scene" class="form-select mt-2" @disabled($storyRun&&!$completedStoryRun)><option value="">整片制作</option>@foreach($storyboardPreflight['snapshot']['scenes'] as $scopeOption)<option value="{{ $scopeOption['scene_id'] }}" @selected($scopeScene===$scopeOption['scene_id'])>仅第{{ $scopeOption['ordinal'] }}段试生成 · {{ $scopeOption['seconds'] }}秒</option>@endforeach</select></label><button class="btn ws-primary" type="submit" @disabled(($storyRun&&!$completedStoryRun)||(!$storyboardPreflight['quote_current']&&(!$storyboardPreflight['handoff_current']||!$storyboardPreflight['fresh'])))>{{ ($storyRun&&!$completedStoryRun)?'已创建制作任务':($storyboardPreflight['quote_current']?'确认方案与费用':'重新核价') }}</button><p class="ws-note mt-3" data-storyboard-production-status role="status" aria-live="polite"></p><a class="btn ws-primary" data-storyboard-production-result hidden>查看新版成片</a><details class="ws-note mt-3" data-production-error-details hidden><summary>查看制作详情</summary><p data-production-error-text></p></details>
@if($storyPlan && app(\App\Services\Marketing\MarketingUploadCheckpoint::class)->visible($project->id))
<div class="mt-3"><button class="btn btn-outline-secondary" type="button" data-upload-checkpoint>上传首帧并核对积分</button><p class="ws-note mt-2" data-upload-checkpoint-status role="status" aria-live="polite">每次只上传一张，保存链接并暂停；费用未知时不继续生成。</p></div>
@endif
</form><script src="{{ asset('marketing-studio/generation-billing.js') }}?v={{ filemtime(public_path('marketing-studio/generation-billing.js')) }}" defer></script><script src="{{ asset('marketing-studio/storyboard-production.js') }}?v={{ filemtime(public_path('marketing-studio/storyboard-production.js')) }}" defer></script>
</aside></div><a class="btn btn-outline-secondary mt-3" href="{{ $link('materials') }}">返回分镜与素材</a>

<details class="ws-card mt-3"><summary>历史制作批次与报价 · 不代表当前工作稿已完成</summary>
@forelse($storyboardPreflight['history'] as $historyPlan)
@php($historyRun=$storyboardPreflight['batches']->firstWhere('plan_id',$historyPlan->id))
<div class="ws-tip mt-2"><strong>{{ ($historyPlan->manifest['recipe']??'')==='storyboard-production-v1'?'分镜 v'.($historyPlan->manifest['story_version']??'?'):'旧流程制作记录' }}</strong> · {{ $historyPlan->created_at->format('Y-m-d H:i') }} · {{ $historyRun?->status??$historyPlan->status }}<small class="d-block">方案编号 {{ $historyPlan->id }}@if($historyRun) · 批次 {{ $historyRun->id }}@endif</small>@if($historyRun?->result['film_id']??null)<a href="{{ route('marketing.workspace.show',['project'=>$project->id,'screen'=>'finished','film'=>$historyRun->result['film_id']]) }}">查看该批次成片</a>@endif</div>
@empty<p class="ws-note mt-2">暂无制作批次记录。</p>@endforelse
<a class="btn btn-outline-secondary mt-3" href="{{ $link('finished') }}">查看旧成片与版本历史</a></details>
