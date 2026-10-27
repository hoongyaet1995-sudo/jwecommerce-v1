@php
$wb=$clipWorkbench;$row=$wb['current'];$clip=$row['clip'];$input=$row['input'];
$clipLink=fn($n)=>route('marketing.workspace.show',['project'=>$project->id,'screen'=>'confirmation','scene'=>$n]);
$busy=$clip&&in_array($clip->status,\App\Services\Marketing\MarketingClipProduction::ACTIVE,true);
$recoverable=$clip&&($clip->raw_path||$clip->provider_task_id||!empty($clip->result['provider_results']));
@endphp
<link rel="stylesheet" href="{{ asset('marketing-studio/workspace-video.css') }}?v={{ filemtime(public_path('marketing-studio/workspace-video.css')) }}">
<div class="ws-split ws-media-split ws-clip-workbench">
 <section class="ws-card">
  <h2>逐段制作，先看效果</h2><p class="text-secondary">选择一段，核对画面与口播。已完成 {{ count($wb['completed']) }} / {{ count($wb['rows']) }} 段。</p>
  <div class="ws-confirm-strip">
   @foreach($wb['rows'] as $item)
   <a class="ws-confirm-thumb {{ $item['number']===$wb['selected']?'border-success':'' }}" href="{{ $clipLink($item['number']) }}" @if($item['number']===$wb['selected']) aria-current="true" @endif>
    @if($item['input'])<img src="{{ route('marketing.asset',[$project->id,$item['input']['image']['asset_id']]) }}" alt="第{{ $item['number'] }}段生产首帧">@else<div class="ws-empty"><i class="bi bi-image"></i></div>@endif
    <div><strong>第{{ $item['number'] }}段 · {{ $item['scene']['duration'] }}秒</strong><small>{{ $item['clip']?($wb['labels'][$item['clip']->status]??$item['clip']->status):($item['pilot']?'已有小样可收纳':($item['error']?'资料待处理':'待制作')) }}</small></div>
   </a>
   @endforeach
  </div>
  <h3 class="mt-4">第{{ $row['number'] }}段 · {{ $clip?->status==='completed'?'制作结果':'核对本段内容' }}</h3>
  @if($clip?->status==='completed')<div class="ws-finished-player"><video controls playsinline preload="metadata" src="{{ route('marketing.workspace.clips.preview',[$project->id,$clip->id]) }}" aria-label="第{{ $row['number'] }}段视频预览"></video></div>
  @elseif($input)<p class="small text-secondary">本段使用单首帧生成连续镜头，尾帧与补充图不发送给此路线。</p><a class="d-block ws-finished-player" href="{{ route('marketing.asset',[$project->id,$input['image']['asset_id']]) }}" target="_blank" rel="noopener"><img class="w-100 h-100" style="object-fit:contain" src="{{ route('marketing.asset',[$project->id,$input['image']['asset_id']]) }}" alt="当前选定的第{{ $row['number'] }}段完整首帧"></a>@endif
  @if($input)
   <div class="ws-tip mt-3"><div><strong>口播 · {{ $input['language'] }}</strong><p class="mb-0 mt-2">{{ $input['spoken_text']?:'本段无口播' }}</p></div></div>
   <details class="border rounded-3 p-3 mt-3"><summary>镜头设计与后期字幕</summary>
    @foreach(['purpose'=>'镜头目的','subject'=>'人物与调度','framing'=>'构图与机位','motion'=>'运镜与节奏','lighting'=>'光线与背景','continuity'=>'连续性'] as $key=>$label)<p class="small mt-3"><strong>{{ $label }}：</strong>{{ $input['director'][$key]??'' }}</p>@endforeach
    <p class="small mb-0"><strong>独立后期字幕：</strong><span class="d-block text-break" style="white-space:pre-line">{{ $input['subtitle']?:'无' }}</span></p>
   </details>
  @endif
  <a class="d-inline-block small mt-4" href="{{ $link('materials') }}"><i class="bi bi-arrow-left"></i> 返回分镜与素材调整内容</a>
 </section>
 <section class="ws-card ws-chat">
  <h2>{{ $clip?->status==='completed'?'检查效果，继续下一段':'第'.$row['number'].'段 · 制作安排' }}</h2>
  @if($row['error'])<div class="alert alert-warning">{{ $row['error'] }}</div><a class="btn ws-primary" href="{{ $link('materials') }}">返回分镜与素材</a>
  @elseif($busy)
   <div class="ws-tip" role="status"><i class="bi bi-hourglass-split"></i><span data-video-state="{{ $clip->id }}">{{ $wb['labels'][$clip->status] }}</span></div><p class="text-secondary mt-3">已保存本段任务。可以关闭页面，返回后继续查看；无需再次点击生成。</p><p class="small text-secondary" data-video-poll-note>正在自动读取进度…</p>
  @elseif($clip?->status==='completed')
   <div class="ws-tip"><i class="bi bi-check-circle"></i> 本段已保存，可以播放检查画面、口播和字幕。</div>
   @if($clip->origin_run_id)<p class="small text-secondary mt-3">已收纳此前验收的小样，本次没有重新收费生成。</p>@endif
   @php($nextRow=collect($wb['rows'])->first(fn($r)=>$r['clip']?->status!=='completed'))
   @if($nextRow)<a class="btn ws-primary w-100 mt-4" href="{{ $clipLink($nextRow['number']) }}">制作第{{ $nextRow['number'] }}段 <i class="bi bi-arrow-right"></i></a>@endif
  @elseif($clip&&in_array($clip->status,['failed','unknown'],true))
   <div class="alert alert-warning">{{ $clip->error_message }}</div>
   @if(in_array($clip->error_code,['provider_failed','native_request_rejected'],true))
    <p class="small text-secondary">供应商已明确返回失败或拒绝请求。先处理提示并核对供应商记录；再次制作需要重新核价和确认，可能产生新的费用。</p><form method="post" action="{{ route('marketing.workspace.clips.quote',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="scene" value="{{ $row['number'] }}"><input type="hidden" name="asset" value="{{ $input['image']['asset_id'] }}"><input type="hidden" name="request_id" value="{{ Str::uuid() }}"><button class="btn ws-primary w-100">重新核价，再确认是否制作</button></form>
   @elseif($recoverable)<form method="post" action="{{ route('marketing.workspace.clips.retry',[$project->id,$clip->id]) }}">@csrf<button class="btn ws-primary w-100">{{ $clip->raw_path?'重试本地字幕处理':'重新查询已有视频任务' }}</button></form>
   @elseif($clip->status==='failed'&&!isset($clip->result['submission_claimed']))
    <p class="small text-secondary">本次尚未提交收费视频请求。处理提示的问题后，可重新核价并确认。</p><form method="post" action="{{ route('marketing.workspace.clips.quote',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="scene" value="{{ $row['number'] }}"><input type="hidden" name="asset" value="{{ $input['image']['asset_id'] }}"><input type="hidden" name="request_id" value="{{ Str::uuid() }}"><button class="btn ws-primary w-100">重新查看本段费用</button></form>
   @else<p class="small">请先在 Evolink 核对该次请求记录。结果未明确前，系统保留记录并停止重复收费提交。</p>@endif
  @elseif($clip?->status==='quoted')
   <div class="ws-tip"><div><strong>本段视频估价 US${{ number_format($clip->quote['estimate_microusd']/1000000,2) }}</strong><p class="mb-0 mt-2">{{ number_format($clip->quote['credits'],2) }} credits · {{ $input['seconds'] }}秒 · 720p · 9:16</p></div></div>
   <p class="small text-secondary mt-3">Seedance 2.0 · 单首帧 · {{ $input['payload']['generate_audio']?'原生同步口播':'无声' }}。字幕本地合成。按供应商实际记录扣费；失败不自动重发收费请求。余额不足会提示 top up。</p><p class="small text-secondary">报价至 {{ $clip->expires_at->timezone('Asia/Kuala_Lumpur')->format('m-d H:i') }} 有效。确认后会将所选 AI 首帧上传给 Evolink。</p>
   <form method="post" action="{{ route('marketing.workspace.clips.authorize',[$project->id,$clip->id]) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><label class="d-flex gap-2 small my-3"><input type="checkbox" name="consent" value="1" required><span>我确认本段镜头、所选首帧、口播与费用，授权生成这一段视频及本地字幕。</span></label><button class="btn ws-primary w-100">确认费用，生成第{{ $row['number'] }}段</button></form>
  @elseif($row['pilot'])
   <div class="ws-tip"><i class="bi bi-check-circle"></i> 这段已有验收过的小样，可以直接收纳。</div><p class="small text-secondary mt-3">复用已生成的原片，再做本地字幕处理；新增视频生成费用 US$0。</p>
   <form method="post" action="{{ route('marketing.workspace.clips.import',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="run" value="{{ $row['pilot']->id }}"><label class="d-flex gap-2 small my-3"><input type="checkbox" name="consent" value="1" required><span>将这段已有小样收纳到当前制作，保留原声并合成本段字幕。</span></label><button class="btn ws-primary w-100">收纳已有片段（不收费）</button></form>
  @else
   <p class="text-secondary">先核对本段首帧与口播，再查看费用。此操作不上传图片、不开始视频生成。</p><form method="post" action="{{ route('marketing.workspace.clips.quote',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="scene" value="{{ $row['number'] }}"><input type="hidden" name="request_id" value="{{ Str::uuid() }}"><label class="form-label" for="clip-image">用于本段的 AI 首帧</label><select class="form-select mb-3" name="asset" id="clip-image">@foreach($row['options'] as $option)<option value="{{ $option['asset_id'] }}">{{ $option['label'] }}</option>@endforeach</select><button class="btn ws-primary w-100">查看本段费用（免费）</button></form>
  @endif
  @if(count($wb['completed'])===count($wb['rows']))
   <hr class="my-4"><h3>片段已齐，准备整片</h3><p class="small text-secondary">按故事顺序直接切镜，保留各段原声，独立叠加字幕与结尾行动提示。本地合成不新增 AI 费用；跨段声线、切镜和字幕效果需要在成片中验收。</p>
   @if($wb['assembly_error'])<div class="alert alert-warning">{{ $wb['assembly_error'] }}</div><a href="{{ $link('materials') }}">返回调整衔接设计</a>
   @elseif($wb['film'])<a class="btn ws-primary w-100" href="{{ $link('finished') }}">查看整片{{ $wb['film']->status==='completed'?'并验收':'合成进度' }} <i class="bi bi-arrow-right"></i></a>
   @else<form method="post" action="{{ route('marketing.workspace.films.assemble',$project->id) }}">@csrf<input type="hidden" name="version" value="{{ $project->version }}">@foreach($wb['completed'] as $id)<input type="hidden" name="clips[]" value="{{ $id }}">@endforeach<label class="d-flex gap-2 small my-3"><input type="checkbox" name="consent" value="1" required><span>按当前片段及衔接安排合成整片，完成后再验收。</span></label><button class="btn ws-primary w-100">合成整片（本地）</button></form>@endif
  @endif
 </section>
</div>
@include('marketing.workspace.video-poll')
