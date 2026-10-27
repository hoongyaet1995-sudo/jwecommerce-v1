@php
$director=$revision?->document['director_plan']??null;
$shots=$director['shots']??[];
$package=isset($revision?->document['material_package'])?app(\App\Services\Marketing\MarketingMaterialPackage::class)->inspectReferences($project,$revision->document['material_package']):null;
$packageFresh=$package&&$package['source_digest']===app(\App\Services\Marketing\MarketingMaterialPackage::class)->digest($project,$revision);
$accepted=$director&&($revision->context['director_review']['plan_digest']??'')===hash('sha256',json_encode($director,JSON_THROW_ON_ERROR));
$transitionState=$revision?app(\App\Services\Marketing\MarketingTransitionPlanning::class)->state($project,$revision):['status'=>'not_required','ready'=>true,'adopted'=>true];
$transitionNeedsReview=!$transitionState['ready']&&($transitionState['status']!=='needs_materials'||!$transitionState['adopted']);
$repairService=app(\App\Services\Marketing\MarketingMaterialRepair::class);$repairRun=$revision?$repairService->latest($project):null;$repairFresh=false;if($repairRun&&$repairRun->status!=='completed')try{$repairService->assertCurrent($project,$repairRun);$repairFresh=$repairRun->status!=='quoted'||now()->lt($repairRun->usage['expires_at']??now());}catch(\Throwable){}$repairNeeded=$revision&&($repairService->eligible($project)||$repairFresh);
$currentTask=(!$accepted||$transitionNeedsReview)?0:(!$packageFresh||$package['status']==='awaiting_inputs'?1:2);
$taskLabels=['审阅镜头与口播','补充必要真实参考','核对素材制备计划'];
$taskCopy=['按段看清画面与表达，确认后再检查需要哪些参考资料。','只收集分镜确实需要的真实依据，其余内容由AI准备。','参考与制备范围已整理；图片生成仍需单独确认费用。'];
if($accepted&&$transitionNeedsReview){$taskLabels[0]='确认跨段衔接与素材复用';$taskCopy[0]='逐一安排前段怎样结束、后段怎样开始，再核对实际首尾图。';}
if($repairNeeded){$currentTask=0;$taskLabels[0]='修正未通过的首尾图片';$taskCopy[0]='先看AI的定向修正范围，再确认费用并自动复检。';}
$referenceAssets=$project->assets()->where('authorized',true)->where('mime','like','image/%')->get();
$pendingFacts=$revision?app(\App\Services\Marketing\MarketingRequirementReview::class)->pending($project,$revision):['gaps'=>[],'items'=>[]];
$imagesReviewed=$packageFresh&&count($package['outputs'])>0&&collect($package['outputs'])->every(fn($o)=>($o['status']??'')==='approved');
$singleClipEntry=config('marketing.workspace_video.enabled')&&$accepted&&$imagesReviewed;
$productionBlocker=null;
$productionReady=false;
if($accepted&&$packageFresh&&count($pendingFacts['gaps'])===0){try{app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->assertReady($project,$revision);if(!$transitionState['ready'])throw new \RuntimeException('请先补齐跨段衔接设计并核对首尾图复用。');$productionReady=true;}catch(\Throwable $e){$productionBlocker=$e->getMessage();}}

 $imageStoryMismatch=$packageFresh&&($project->concepts[$project->selected_concept]['video_type']??'')==='presenter'&&!collect($package['requirements'])->contains(fn($n)=>in_array($n['kind'],['real_person','synthetic_person']));
 $imageService=app(\App\Services\Marketing\MarketingMaterialImages::class);
 $imageRun=$imageService->latest($project);
 $imageFresh=false;
 if($imageRun&&$packageFresh&&$package['status']!=='awaiting_inputs')try{$imageFresh=($imageRun->usage['contract']??'')===\App\Services\Marketing\MarketingMaterialImages::CONTRACT&&$imageService->fingerprint($project)===($imageRun->usage['fingerprint']??'')&&($imageRun->status!=='quoted'||now()->lt($imageRun->usage['expires_at']));}catch(\Throwable){}
 $imageUsage=$imageRun?->usage??[];
 $imageClean=$imageFresh&&($imageUsage['design_version']??'')===\App\Services\Marketing\MarketingMaterialImageDesign::VERSION;
 $imageActive=$imageClean&&$imageRun?->status==='running';

@endphp
<link rel="stylesheet" href="{{ asset('marketing-studio/material-review-sample.css') }}?v={{ filemtime(public_path('marketing-studio/material-review-sample.css')) }}">
<div class="mr-sample" data-material-workspace data-project="{{ $project->id }}" data-current-task="{{ $currentTask }}">
 <div class="row g-3">
  <div class="col-lg-7"><section class="ws-card h-100">
   <div class="d-flex justify-content-between gap-3 align-items-start"><div><span class="mr-eyebrow">选定的故事</span><h2 class="mt-2">{{ $project->concepts[$project->selected_concept??0]['title']??'分镜与素材' }}</h2><p class="text-secondary small">{{ $summary['平台与时长'] }} · {{ $brief['language']??'语言待确认' }}</p></div>
    @if($director)<div class="dropdown"><button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-label="更多镜头操作"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-mw-adjust>提出镜头调整／重新设计</button></li>@if($package)<li><button class="dropdown-item" type="button" data-mw-replan>重新整理素材计划</button></li>@endif</ul></div>@endif
   </div>
   <div class="mr-story-preview">@if($image)<img src="{{ $image }}" alt="当前项目商品参考图，非生成成片">@else<i class="bi bi-film"></i>@endif<span>原始参考图 · 待AI制备生产素材</span></div>
   <div class="mr-beats">@forelse($revision?->document['scenes']??[] as $scene)<div><span>{{ $loop->iteration }}</span><div><strong>第{{ $loop->iteration }}段 · {{ $scene['duration'] }}秒</strong><small>{{ $scene['voiceover']?:'无口播' }}</small></div></div>@empty<div><span>1</span><div><strong>先准备分镜</strong><small>从已选故事开始安排画面与表达。</small></div></div>@endforelse</div>
   <p class="small text-secondary mb-0 mt-3">先确认这支视频讲什么、怎么拍，再准备生产图片。</p>
  </section></div>
  <div class="col-lg-5"><section class="ws-card h-100 mr-task-panel"><span class="mr-eyebrow">现在先做这一件事</span>
   @if($singleClipEntry)
   <h2 class="mt-2">进入逐段制作，先看效果</h2><p class="text-secondary">镜头设计与 AI 素材已保存。接下来逐段核对首帧、口播和费用，再生成视频。</p>
   <div class="ws-tip"><i class="bi bi-info-circle"></i> 每段制作单独确认；已有图片修正报告保留，单首帧路线不要求重画尾帧。</div>
   <div class="mt-auto pt-4"><a class="btn ws-primary w-100" href="{{ $link('confirmation') }}">进入逐段制作 <i class="bi bi-arrow-right"></i></a><button type="button" class="btn btn-link w-100 mt-2 small" data-mw-start>查看分镜与素材准备</button></div>
   @else
   <h2 class="mt-2">{{ $taskLabels[$currentTask] }}</h2><p class="text-secondary">{{ $taskCopy[$currentTask] }}</p>
   <ol class="mr-checklist">@foreach($taskLabels as $label)<li class="{{ $loop->index===$currentTask?'current':($loop->index<$currentTask?'done':'') }}"><span>{{ $loop->iteration }}</span><div><strong>{{ $label }}</strong><small>{{ ['逐段查看导演设计','其余虚拟内容由AI准备','先看素材与费用安排，再生成'][$loop->index] }}</small></div><i class="bi {{ $loop->index<$currentTask?'bi-check2':($loop->index===$currentTask?'bi-arrow-right':'bi-lock') }}"></i></li>@endforeach</ol>
   <div class="mt-auto pt-3"><button type="button" class="btn ws-primary w-100" data-mw-start>{{ $currentTask===0?'开始审阅与准备':($currentTask===1?'继续准备参考资料':'查看素材制备计划') }} <i class="bi bi-arrow-right"></i></button><p class="small text-secondary text-center mt-2 mb-0">图片制备与视频制作分别确认费用</p></div>
   @endif
  </section></div>
 </div>
 <details class="ws-card mt-3 mr-question"><summary><i class="bi bi-chat-dots me-2"></i>有疑问？向项目AI助手提问</summary><p class="small text-secondary mt-3">例如：“需要老板照片吗？”“为什么要补这张参考？”</p>@include('marketing.workspace.composer')</details>
 <div class="modal fade" id="materialWorkspaceWizard" tabindex="-1" aria-labelledby="materialWorkspaceTitle"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content rounded-4">
  <div class="modal-header border-0"><div><span class="mr-eyebrow">分镜与素材 · 准备向导</span><h2 class="modal-title fs-4 mt-1" id="materialWorkspaceTitle">{{ $project->concepts[$project->selected_concept??0]['title']??'准备分镜与素材' }}</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭准备向导"></button></div>
  <div class="mr-modal-progress" aria-label="准备进度">@foreach(['审阅分镜','真实参考','制备计划'] as $label)@if(!$loop->first)<i class="bi bi-chevron-right"></i>@endif<span data-mw-label="{{ $loop->index }}">{{ $loop->iteration }} {{ $label }}</span>@endforeach</div>
  <div class="modal-body">
   @if($errors->any())<div class="alert alert-warning small">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
   <section data-mw-stage="0"><div class="mr-step-intro"><h3>先确认：这是不是你想讲的故事？</h3><p>每次只看一段。确认镜头与口播后，再进入素材准备。</p></div>
    @if($director)
     <div class="mr-scene-tabs" role="group" aria-label="选择故事段落">@foreach($shots as $shot)<button type="button" class="btn btn-sm {{ $loop->first?'ws-primary':'btn-light' }}" data-mw-scene-go="{{ $loop->index }}">第{{ $loop->iteration }}段 · {{ $shot['start'] }}–{{ $shot['end'] }}秒</button>@endforeach</div>
     @foreach($shots as $shot)<article data-mw-scene="{{ $loop->index }}" @if(!$loop->first) hidden @endif><div class="mr-scene-brief"><span class="mr-eyebrow">这一段要传达什么</span><p class="fw-semibold my-2">{{ $shot['purpose'] }}</p><div class="mr-speech"><i class="bi bi-mic"></i><p class="mb-0">{{ $shot['voiceover']?:'无口播' }}</p></div></div>
      <div class="mr-detail-grid">@foreach(['subject'=>'人物与调度','framing'=>'构图与机位','motion'=>'运镜与节奏','lighting'=>'光线与背景','continuity'=>'连续性','beats'=>'内部动作节拍','voiceover'=>'口播','subtitle'=>'字幕','checks'=>'验收重点'] as $key=>$label)<details><summary>{{ $label }}</summary><div class="small">@if($key==='beats')@foreach($shot[$key] as $beat)<p>{{ $beat['start'] }}–{{ $beat['end'] }}秒 · {{ $beat['action'] }}</p>@endforeach @elseif($key==='checks')@foreach($shot[$key] as $check)<p>{{ $check }}</p>@endforeach @else<p>{{ $shot[$key]?:'无' }}</p>@endif</div></details>@endforeach</div>
     </article>@endforeach
     <details class="mt-3 small"><summary>全片设计摘要与注意事项</summary><p class="mt-2">{{ $director['summary'] }}</p>@foreach($director['notes'] as $note)<p>{{ $note }}</p>@endforeach</details>
    @elseif($revision)
     <div class="ws-note mb-3">已保存叙事分镜。下一步由文字AI补充镜头设计，不会生成图片或视频。</div>@foreach($revision->document['scenes']??[] as $scene)<details class="border rounded-3 p-3 mb-2"><summary>第{{ $loop->iteration }}段 · {{ $scene['duration'] }}秒</summary><p class="small mt-2">{{ $scene['visual'] }}</p><p class="small">口播：{{ $scene['voiceover']?:'无口播' }}</p></details>@endforeach
    @else<div class="ws-note">{{ $project->selected_concept===null?'先选择一个创意方向，再安排分镜。':'先根据选定故事准备分镜，再设计镜头。' }}</div>@endif
    @foreach($revision?->document['gaps']??[] as $gap)<p class="small text-warning mt-2">{{ $gap }}</p>@endforeach
    @if($director&&$accepted)@include('marketing.workspace.transition-review')@endif
    <div class="mr-adjust" data-mw-adjust-panel hidden><label class="fw-semibold small" for="mw-adjustment">你想调整什么？</label><textarea class="form-control mt-2" id="mw-adjustment" rows="2" maxlength="2000" placeholder="例如：镜头保持稳定，人物手势少一点…"></textarea><p class="small text-secondary mt-2">重新设计会发起一次文字AI请求；原故事、口播和字幕保持不变。</p><button type="button" class="btn btn-outline-secondary btn-sm" data-creative-action="director" data-feedback-target="#mw-adjustment">按这些建议重新设计镜头</button></div>
   </section>
   <section data-mw-stage="1" hidden id="material-package"><div class="mr-step-intro"><h3>只补AI不能可靠推断的真实资料</h3><p>先整理各段实际需要的参考资料。虚拟人物、虚构场景和排版由AI准备；原图仅作参考。</p></div>
    @if(!$package)<div class="ws-note">镜头设计已准备。点击下方继续，由文字AI整理素材需求与首帧／尾帧规划；不会生成图片。</div>
    @elseif(!$packageFresh)<div class="alert alert-warning">来源已变化。请重新整理素材计划，旧确认不会用于制作。</div>
    @else
     <p class="small text-secondary">{{ $package['summary'] }}</p><div class="mr-reference-grid">@forelse(array_filter($package['requirements'],fn($need)=>$need['owner']==='employee') as $need)<div class="mr-reference-card"><div class="d-flex justify-content-between gap-2"><strong>{{ $need['name'] }}</strong><span class="badge bg-light text-dark">{{ $need['reference']?'参考已提供':(($need['status']??'')==='reference_invalid'?'参考已失效':($need['required']?'待补充':'可选')) }}</span></div><p class="small text-secondary mt-2">第{{ implode('、',$need['scene_numbers']) }}段 · {{ $need['reason'] }}</p><p class="small">{{ $need['question'] }}</p>@if($need['reference'])<p class="small text-success">参考已保存，仍须AI制备生产图。</p>@endif @php($fresh=$packageFresh)@include('marketing.workspace.material-reference-fields')</div>@empty<div class="mr-reference-card"><i class="bi bi-check-circle text-success"></i><strong class="ms-2">目前无需你补充真实资料</strong><p class="small text-secondary mt-2 mb-0">可以继续查看AI素材制备计划。</p></div>@endforelse</div>
     <div class="mr-ai-work"><i class="bi bi-stars"></i><div><strong>AI负责准备的内容</strong>@forelse(array_filter($package['requirements'],fn($need)=>$need['owner']==='ai') as $need)<details class="small mt-2"><summary>{{ $need['name'] }} · 第{{ implode('、',$need['scene_numbers']) }}段</summary><p class="mt-2 mb-1">{{ $need['preparation'] }}</p>@if($need['kind']==='graphic')<small>这是排版内容，无需上传图片。</small>@endif</details>@empty<p class="small mb-0 mt-1">没有额外参考要求；生产图片仍由AI制备。</p>@endforelse</div></div>
    @endif
    <div class="mr-adjust" data-mw-replan-panel hidden><p class="small">重新整理会发起一次文字AI请求，并重新检查素材范围；来源未变且需求相同的有效参考可复用。</p><button type="button" class="btn btn-outline-secondary btn-sm" data-creative-action="material_plan">确认重新整理素材计划</button></div>
   </section>
   <section data-mw-stage="2" hidden>@if(!$transitionState['ready'])<div class="alert alert-warning small">跨段衔接或素材复用尚未完成。<button class="btn btn-outline-secondary btn-sm ms-2" type="button" data-mw-go="0">查看衔接与素材缺口</button></div>@endif<div class="mr-step-intro"><h3>看看接下来会准备哪些画面</h3><p>这里先核对制备范围。尚未生成或批准的图片，不会作为视频输入。</p></div>
    @if($packageFresh)
     @foreach(collect($package['outputs'])->groupBy('scene_number') as $sceneNumber=>$outputs)<details class="mb-3" @if($loop->first) open @endif><summary class="fw-semibold mb-3">第{{ $sceneNumber }}段 · 素材制备计划</summary><div class="mr-output-grid">@foreach($outputs as $output)<div class="mr-output-card"><div><i class="bi {{ ['start_frame'=>'bi-play-circle','end_frame'=>'bi-stop-circle','extra_image'=>'bi-images'][$output['role']] }}"></i><span>{{ ($output['status']??'')==='approved'?'已批准':'待AI制备／审阅' }}</span></div><strong>{{ ['start_frame'=>'开场关键帧','end_frame'=>'结束关键帧','extra_image'=>'补充参考图'][$output['role']] }}</strong><details class="small mt-2"><summary>查看制备要求</summary><p class="mt-2">{{ $output['preparation'] }}</p></details></div>@endforeach</div></details>@endforeach
     @if(!isset($imageUsage['repair_context']))@include('marketing.workspace.material-image-workflow')@else<p class="small text-secondary">修正任务在“审阅分镜”中处理；通过并采用后即可进入制作确认。</p>@endif
    @else<div class="ws-note">先完成上一步的素材需求整理与真实参考收集。</div>@endif
   </section>
  </div>
  <div class="modal-footer justify-content-between">
   <button type="button" class="btn btn-outline-secondary" data-mw-back><i class="bi bi-arrow-left"></i> 上一步</button>
   <div data-mw-footer="0">
    @if($project->selected_concept===null)<a class="btn ws-primary" href="{{ $link('concepts') }}">选择创意方向 <i class="bi bi-arrow-right"></i></a>
    @elseif(!$revision)<button type="button" class="btn ws-primary" data-creative-action="storyboard">准备分镜与素材安排（文字AI） <i class="bi bi-arrow-right"></i></button>
    @elseif(!$director)<button type="button" class="btn ws-primary" data-creative-action="director">补充镜头设计（文字AI） <i class="bi bi-arrow-right"></i></button>
    @elseif(!$accepted)<button type="button" class="btn ws-primary" data-creative-action="confirm_director">确认镜头，检查素材 <i class="bi bi-arrow-right"></i></button>
    @elseif($repairNeeded)
     @if($repairFresh&&$repairRun->status==='quoted')<button type="button" class="btn ws-primary" data-repair-op="authorize">确认范围与费用，开始定向修正</button>
     @elseif($repairFresh&&$repairRun->status==='planning')<button type="button" class="btn ws-primary" disabled>AI正在准备修正方案</button>
     @elseif($repairFresh&&$repairRun->status==='running')<button type="button" class="btn ws-primary" data-repair-op="advance">继续修正与复检</button>
     @elseif($repairFresh&&$repairRun->status==='awaiting_batch_review')<button type="button" class="btn ws-primary" data-repair-op="apply">采用修正结果，继续准备</button>
     @elseif($repairFresh&&$repairRun->status==='needs_attention')@if(($repairRun->usage['pause_reason']??'')==='budget')<button type="button" class="btn ws-primary" data-repair-op="resume-budget">确认提高停止线，继续修正</button>@else<button type="button" class="btn ws-primary" disabled>已暂停，请查看修正报告</button>@endif
     @else<button type="button" class="btn ws-primary" data-repair-op="plan">准备定向修正方案</button>@endif
    @elseif(in_array($transitionState['status'],['missing','stale']))<button type="button" class="btn ws-primary" data-creative-action="transitions">整理衔接与检查首尾图</button>
    @elseif(!$transitionState['ready']&&(!$transitionState['adopted']||$transitionState['status']==='needs_review'))<button type="submit" form="transition-review-form" class="btn ws-primary">{{ $transitionState['status']==='needs_materials'?'采用衔接设计，准备素材':'确认衔接与首尾图复用' }}</button>
    @else<button type="button" class="btn ws-primary" data-mw-go="1">镜头已确认，检查素材 <i class="bi bi-arrow-right"></i></button>@endif
   </div>
   <div data-mw-footer="1" hidden>@if(!$packageFresh)<button type="button" class="btn ws-primary" data-creative-action="material_plan">整理素材需求（文字AI） <i class="bi bi-arrow-right"></i></button>@elseif($package['status']==='awaiting_inputs')<button type="button" class="btn ws-primary" disabled>请先保存必要真实参考</button>@else<button type="button" class="btn ws-primary" data-mw-go="2">确认参考，查看制备计划 <i class="bi bi-arrow-right"></i></button>@endif</div>
   <div data-mw-footer="2" hidden>@if($imageStoryMismatch)<button type="button" class="btn ws-primary" data-creative-action="storyboard" data-feedback-target="#mw-story-repair">先修正分镜为人物口播</button>
    @elseif($imageFresh&&!$imageClean)<button type="button" class="btn ws-primary" data-image-op="quote">按无字幕规则重新确认费用</button>
    @elseif($productionReady)<button type="button" class="btn ws-primary" data-creative-action="confirm_storyboard">确认分镜与素材，进入制作确认 <i class="bi bi-arrow-right"></i></button>@else
    @if($imagesReviewed&&!$productionReady)<button type="button" class="btn ws-primary" disabled>请先完成待确认资料</button>
    @elseif(isset($imageRun)&&$imageFresh&&$imageRun->status==='awaiting_batch_review')<button type="button" class="btn ws-primary" data-image-op="approve-batch">确认整套素材并保存</button>
    @elseif(isset($imageRun)&&$imageFresh&&$imageRun->status==='needs_attention'&&($imageUsage['pause_reason']??'')==='budget')<button type="button" class="btn ws-primary" data-image-op="resume-budget">确认提高停止线，继续制备</button>
    @elseif(isset($imageRun)&&$imageFresh&&$imageRun->status==='needs_attention'&&!empty($imageUsage['auto_review']))<button type="button" class="btn ws-primary" disabled>已暂停，请查看上方原因</button>
    @elseif(isset($imageRun)&&$imageFresh&&$imageRun->status==='quoted')<button type="button" class="btn ws-primary" data-image-op="authorize">确认费用并生成本批图片</button>
    @elseif(isset($imageRun)&&$imageFresh&&$imageRun->status==='running')<button type="button" class="btn ws-primary" data-image-op="advance">继续检查生成进度</button>
    @elseif(isset($imageRun)&&$imageFresh&&$imageRun->status==='needs_attention'&&collect($imageUsage['outputs'])->contains(fn($o)=>in_array($o['status'],['unknown','submitting','submitted'])))<button type="button" class="btn btn-outline-secondary" data-image-op="advance">检查已提交任务</button>
    @elseif(isset($imageRun)&&$imageFresh&&collect($imageUsage['outputs'])->contains('status','review'))<button type="button" class="btn ws-primary" disabled>请先逐张审阅生成图片</button>
    @else<button type="button" class="btn ws-primary" data-image-op="quote">确认素材计划，查看生成费用</button>@endif
    @endif</div>
  </div>
 </div></div></div>
</div>
<textarea id="mw-story-repair" hidden>当前选定创意为人物口播，但旧分镜误变成商品目录图与文字图形。请按已选故事重新准备叙事分镜，让同一位主持人按已选故事在合适场景口播；未指定真实身份时采用虚拟主持人，已有明确真人身份要求时保留该身份与必要照片需求。商品照片仅约束商品外观。保留现有各段时长、旁白与字幕逐字不变（以请求中当前已保存的逐段原文为准）。不生成图片，不改变真实商业事实，仅在明确需要真实人物身份时索取相应照片；虚拟人物与虚构场景由后续AI制备。</textarea>
<form id="ws-creative-form" method="post" action="{{ route('marketing.workspace.creative',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="request_id" value="{{ (string) Str::uuid() }}"><input type="hidden" name="action"><input type="hidden" name="index"><input type="hidden" name="feedback"></form>
<script src="{{ asset('marketing-studio/material-workspace.js') }}?v={{ filemtime(public_path('marketing-studio/material-workspace.js')) }}" defer></script>
