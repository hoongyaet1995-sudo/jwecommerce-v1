@if(isset($storyboardPreflight))
@include('marketing.workspace.storyboard-preflight')
@else
@if(isset($clipWorkbench))
 @include('marketing.workspace.clip-production')
@elseif(!isset($preflight))
 @include('marketing.workspace.confirmation-legacy')
@else
@php($handoff=$preflight['handoff'])
@php($savedPreflight=$handoff&&$preflightPlan&&$preflightPlan->expires_at->isFuture()&&($preflightPlan->manifest['handoff']['digest']??null)===$handoff['digest'])
<div class="ws-split ws-media-split"><section class="ws-card"><h2>制作前，再确认一次</h2><p class="text-secondary">{{ $summary['平台与时长'] }} · {{ $brief['language']??'语言待确认' }}</p>
 @if($handoff)
 <div class="ws-confirm-strip">@foreach($handoff['scenes'] as $scene)<div class="ws-confirm-thumb"><img src="{{ route('marketing.asset',[$project->id,$scene['frames']['start_frame']['asset_id']]) }}" alt="第{{ $scene['number'] }}段已批准生产首帧"><div><strong><span class="ws-mini-node">{{ $scene['number'] }}</span>镜头 {{ $scene['number'] }} · {{ $scene['planned_seconds'] }}秒</strong><small>{{ $scene['postproduction']['voiceover'] }}</small></div></div>@endforeach</div>
 @if(!($handoff['transitions']['ready']??false))<div class="alert alert-warning small mt-3">请返回Step 3补齐跨段衔接设计及首尾图复用核对；旧图片保留，旧估价不能继续采用。</div>@endif
 <p class="ws-confirm-reference-note">已批准生产帧 · 图片不是生成后的视频预览；字幕与图形由后期合成处理。</p>
 @if($handoff['transitions']['ready']&&count($handoff['transitions']['boundaries']))<details class="border rounded-3 p-3 mt-3"><summary>跨段衔接与后期安排</summary>@foreach($handoff['transitions']['boundaries'] as $join)<p class="small mt-2">第{{ $join['from'] }} → {{ $join['to'] }}段：{{ $join['postproduction'] }}；{{ $join['audio']['instruction'] }}</p>@endforeach</details>@endif
 @foreach($handoff['scenes'] as $scene)<details class="border rounded-3 p-3 mt-3"><summary>第{{ $scene['number'] }}段 · 查看素材交接与导演设计</summary><div class="ws-confirm-strip mt-3">@foreach(['start_frame'=>'首帧','end_frame'=>'尾帧','extra_image'=>'补充参考图'] as $role=>$label)<div class="ws-confirm-thumb" data-frame-role="{{ $role }}"><a href="{{ route('marketing.asset',[$project->id,$scene['frames'][$role]['asset_id']]) }}" target="_blank" rel="noopener"><img src="{{ route('marketing.asset',[$project->id,$scene['frames'][$role]['asset_id']]) }}" alt="第{{ $scene['number'] }}段{{ $label }}"></a><div><strong>{{ $label }}</strong><small>已批准 · {{ $scene['frames'][$role]['visual_review']?'AI质检＋用户确认':'用户审阅确认' }}</small></div></div>@endforeach</div><p class="small mt-3">{{ $scene['director']['purpose'] }}</p><div class="mr-detail-grid">@foreach(['subject'=>'人物与调度','framing'=>'构图与机位','motion'=>'运镜与节奏','lighting'=>'光线与背景','continuity'=>'连续性'] as $key=>$label)<details><summary>{{ $label }}</summary><p class="small">{{ $scene['director'][$key] }}</p></details>@endforeach</div><p class="small mt-3">口播：{{ $scene['postproduction']['voiceover']?:'无' }}</p><p class="small">后期字幕：{{ $scene['postproduction']['subtitle']?:'无' }}</p></details>@endforeach
 <div class="ws-three mt-4"><div class="ws-visual-choice"><i class="bi bi-person-circle"></i>人物<small>按已确认导演设计</small></div><div class="ws-visual-choice"><i class="bi bi-soundwave"></i>声音<small>{{ $handoff['language'] }} · 待音频适配</small></div><div class="ws-visual-choice"><i class="bi bi-badge-cc"></i>字幕<small>独立后期轨道</small></div></div>
 @else<div class="alert alert-warning">{{ $preflight['error'] }}</div>@endif
 <div class="ws-readiness mt-4"><i class="bi bi-clipboard-check"></i><div><h3>核对已保存的分镜与素材</h3><p class="text-secondary mb-0">{{ $handoff?'来源、图片角色、文件及授权已核验。':'请先返回Step 3完成当前版本。' }}</p></div><a class="ms-auto" href="{{ $link('materials') }}">修改分镜与素材</a></div></section>
 <section class="ws-card ws-chat"><h2><i class="bi bi-clipboard-check"></i> 本地制作预检</h2>
 @if($handoff)<div class="ws-tip"><i class="bi bi-check-circle"></i> {{ ($handoff['transitions']['ready']??false)?'本地预检通过':'素材核验通过，跨段衔接待确认' }} · {{ count($handoff['scenes']) }}段／{{ count($handoff['scenes'])*3 }}张已批准生产图</div><p class="small text-secondary mt-3">当前分镜版本 {{ $handoff['revision_number'] }}。保存交接只整理资料，不调用AI或开始制作。</p>
 @if($savedPreflight)<div class="alert alert-success small">当前版本的逐段交接包与本地预检已保存，可关闭后重开。</div>@elseif($preflightPlan)<div class="alert alert-warning small">之前的预检已过期或资料已变化，请重新保存当前交接。</div>@endif
 <form method="post" action="{{ route('marketing.workspace.preflight',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="revision_id" value="{{ $revision->id }}"><input type="hidden" name="request_id" value="{{ (string) Str::uuid() }}"><button type="submit" class="btn ws-primary w-100" @if($savedPreflight) disabled @endif>{{ $savedPreflight?'当前交接与预检已保存':'保存逐段交接与本地预检' }} <i class="bi bi-arrow-right"></i></button></form>
 @else<div class="ws-tip">本地预检未通过，请检查左侧原因。</div>@endif
 @include('marketing.workspace.supplier-quote')
 <h3 class="mt-4">进入制作前还需要</h3><div class="ws-cost-row"><span>视频输入方案</span><strong>{{ ($supplierQuoteFresh??false)?'已完成参数映射':'待选择方案' }}</strong></div><div class="ws-cost-row"><span>配音与字幕合成</span><strong>待适配</strong></div><div class="ws-cost-row total"><span>整片总费用</span><strong>尚未完成核价</strong></div><div class="ws-tip"><i class="bi bi-info-circle"></i> 已准备素材不会自动套用旧制作路线。供应商能力和报价接通后，再单独确认制作费用。</div><button class="btn ws-primary w-100 mt-3" disabled>待完成供应商适配与报价</button><p class="ws-note mt-3">本地预检、视频制作及发布授权相互独立。</p><div class="mt-auto">@include('marketing.workspace.composer')</div></section></div>
@endif

@endif
