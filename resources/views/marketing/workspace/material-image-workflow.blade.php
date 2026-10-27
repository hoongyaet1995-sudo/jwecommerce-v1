@php($automatic=!empty($imageUsage['auto_review']))

<div data-image-workflow data-url="{{ route('marketing.workspace.material.images',[$project->id,'quote']) }}" data-version="{{ $project->version }}" data-run="{{ $imageFresh?$imageRun?->id:'' }}" data-request="{{ (string) Str::uuid() }}" data-running="{{ $imageActive?'1':'0' }}" class="mr-price-note d-block">
 <h3 class="fs-6">图片制备与审阅</h3>
 <p class="small mb-2">确认素材计划后查看本批费用；授权只用于图片制备，不包含视频制作。图片由AI Connect配置的OpenAI GPT Image 2生成。分镜与导演稿决定人物、场景和构图，上传图片只约束真实外观或身份。</p>
 @if($imageFresh&&!$imageClean)<div class="alert alert-warning small">这批图片使用旧版设计，可能把字幕或标题画入画面。原图与用量记录保留，后续生成已停止。请按新的无字幕规则重新确认费用；不会自动重画或扣费。</div>@endif
 <p class="small">生产帧验收：不含字幕、标题条、CTA或预留色块；字幕与图形留给后期合成，安全区保留自然背景。</p>
 @if(count($pendingFacts['gaps']))<div class="alert alert-warning small"><strong>进入制作确认前，还有资料需要确认</strong><p class="mt-2">以下内容来自分镜的资料检查。请确认内容真实；这不会生成图片或授权费用。</p>
 @foreach($pendingFacts['items'] as $fact)@if($fact['kind']==='required_fact')<label class="form-check mb-2"><input type="checkbox" class="form-check-input" data-fact-review value="{{ $fact['key'] }}"><span>{{ $fact['message'] }}</span><small class="d-block text-secondary">分镜原文：{{ $fact['evidence'] }}</small></label>@endif @endforeach
 @if(collect($pendingFacts['items'])->contains('kind','required_fact'))<button type="button" class="btn ws-primary btn-sm" data-image-op="facts">确认勾选的真实资料</button>@endif
 <details class="mt-2"><summary>查看全部待确认项</summary>@foreach($pendingFacts['gaps'] as $gap)<p>{{ $gap }}</p>@endforeach</details></div>@endif
 @if($productionBlocker)<div class="alert alert-warning small">{{ $productionBlocker }}</div>@endif
 @if($productionReady)<div class="alert alert-success small">所有生产图片与必要资料已确认，可以进入制作确认。</div>@endif
 @if($imageStoryMismatch)<div class="alert alert-warning small">当前选定故事为人物口播，但保存的分镜与导演稿只有商品图和文字图形，没有主持人。请先点击下方“修正分镜”，审阅新导演稿后再生成；旧版本会保留。</div>@endif
 @if($imageFresh)
  <p><strong>{{ $imageUsage['model'] }} · 最多{{ count($imageUsage['outputs']) }}张 · {{ $imageUsage['size'] }} · medium质量</strong></p>
  <p class="small">按实际token计费，每100万token：文字输入 US$2.50、图片输入 US$4.00、图片输出 US$15.00。参考图数量与实际用量会影响费用，无法在出图前给出精确总价；不沿用旧版固定每张价格。@if($automatic)每个画面最多生成3次（初稿与2轮定向修正），每次生成后检查一次；不会切换模型或重试结果未知的请求。@else本批最多生成上述数量，无自动重试或模型替换。@endif<a href="{{ $imageUsage['pricing_url'] }}" target="_blank" rel="noopener">官方费率</a> · 核对日期 {{ $imageUsage['pricing_checked_at'] }}</p>
  @if($automatic)<div class="ws-note small mb-3"><strong>AI视觉质检 → 定向修正 → 再质检</strong><p class="mt-2">检查实际图片、人物／商品一致性、无字幕画面及首尾过渡。每帧最多2轮修正，整批最多{{ count($imageUsage['outputs'])*3 }}次图片请求及{{ count($imageUsage['outputs'])*3 }}次视觉质检。最后由你确认整套，AI不会代你采用素材。</p><p>质检模型 {{ $imageUsage['auto_review']['vision']['model'] }}：每100万输入token US$0.20、输出token US$1.20。已知累计用量 US${{ number_format($imageUsage['spent_usd']??0,2) }}；预算停止线 US${{ number_format($imageUsage['auto_review']['spend_stop_usd'],2) }}。</p><p class="mb-0">每次图片请求前预留US$0.50、质检前预留US$0.10，不足则暂停。这是内部停止规则，并非供应商硬性封顶；单次实际费用可能超过预留并跨过停止线。用量未知、资料不确定或修正用尽会暂停。</p></div>@endif
  <details class="small mb-3"><summary>查看每张图的故事与画面设计</summary>@foreach($imageUsage['outputs'] as $planned)<div class="mr-scene-brief mt-2"><strong>第{{ $planned['scene'] }}段 · {{ ['start_frame'=>'首帧','end_frame'=>'尾帧','extra_image'=>'补充参考图'][$planned['role']] }}</strong><p class="mt-2 mb-1">主体与调度：{{ $planned['design']['subject'] }}</p><p class="mb-1">构图：{{ $planned['design']['composition'] }}</p><p class="mb-1">该帧动作：{{ $planned['design']['frame_state']['action'] }}</p><p class="mb-0">光线与环境：{{ $planned['design']['lighting_environment'] }}</p></div>@endforeach</details>
  @if($imageRun->status==='quoted')
   <p class="small">报价有效至 {{ \Illuminate\Support\Carbon::parse($imageUsage['expires_at'])->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i') }}（马来西亚时间）</p><label class="form-check small"><input type="checkbox" class="form-check-input" data-image-consent>我确认本批画面设计、参考使用及上述OpenAI按量计费规则，@if($automatic)授权按上述预算停止规则执行图片生成、视觉质检及每帧最多2轮修正；最后由我确认整套。@else授权最多生成本批数量的图片；每张先审阅，再继续。@endif</label>
  @else
   @if($imageRun->error)<div class="alert alert-warning small">{{ $imageRun->error }}</div>@endif
   <p class="small">{{ $automatic?'AI会依次生成、检查并定向修正；请保持页面开启，关闭后可回来继续。全套通过后只需一次确认，随后保存至 Asset → Media → Images。':($imageActive?'正在制备下一张；完成后会停下来等你审阅，关闭页面后可回来查看。':'请逐张审阅生成结果。通过后保存至 Asset → Media → Images；需要修改的保留记录。') }}</p>
   <div class="mr-reference-grid">
   @foreach($imageUsage['outputs'] as $result)
    <div class="mr-reference-card"><strong>第{{ $result['scene'] }}段 · {{ ['start_frame'=>'开场关键帧','end_frame'=>'结束关键帧','extra_image'=>'补充参考图'][$result['role']] }}</strong>
     <p class="small mt-2">{{ ['planned'=>'尚未提交','submitting'=>'提交处理中','submitted'=>'供应商生成中','review'=>'待你审阅','approved'=>'已通过并入库','rejected'=>'需要调整','failed'=>'生成失败','unknown'=>'提交结果待核对','qa_pending'=>'等待AI视觉质检','qa_submitting'=>'AI正在检查实际图片','qa_unknown'=>'质检结果待核对','repair_pending'=>'等待定向修正','ai_passed'=>'AI质检通过 · 待整套确认','qa_failed'=>'修正上限已用尽','requires_input'=>'需要补充可靠资料'][$result['status']] }}</p>
     @if($result['asset_id'])<img src="{{ route('marketing.asset',[$project->id,$result['asset_id']]) }}" alt="AI生成的{{ $result['id'] }}"><a class="small d-block my-2" href="{{ route('marketing.asset',[$project->id,$result['asset_id']]) }}" target="_blank" rel="noopener">查看原图</a>@endif
     @if(isset($result['actual_usd']))<p class="small">本张按返回用量计算：US${{ number_format($result['actual_usd'],2) }}（以OpenAI账单为准）</p>@endif
     @if(!empty($result['qa']))<details class="small mb-2" open><summary>AI质检 · {{ $result['repairs']??0 }}/2轮修正</summary><p>{{ $result['qa']['summary'] }}</p>@foreach($result['qa']['observations'] as $observation)<p>{{ $observation }}</p>@endforeach</details>@endif
     @if(!empty($result['attempts']))<details class="small mb-2"><summary>查看生成与修正记录（{{ count($result['attempts']) }}版）</summary>@foreach($result['attempts'] as $attempt)<p><a href="{{ route('marketing.asset',[$project->id,$attempt['asset_id']]) }}" target="_blank" rel="noopener">第{{ $loop->iteration }}版</a> · {{ $attempt['qa']['summary']??'待质检' }} @if($attempt['correction'])<span class="d-block">修正：{{ $attempt['correction'] }}</span>@endif</p>@endforeach</details>@endif
     @if($result['status']==='review')<div class="d-flex gap-2">@if($imageClean)<button type="button" class="btn ws-primary btn-sm" data-image-op="review" data-output="{{ $result['id'] }}" data-approve="1">通过并保存，继续</button>@endif<button type="button" class="btn btn-outline-secondary btn-sm" data-image-op="review" data-output="{{ $result['id'] }}" data-approve="0">需要调整</button></div>@endif
    </div>
   @endforeach
   </div>
   @if($imageRun->status==='needs_attention')<p class="small mt-3">请先核对OpenAI用量记录。结果未知的图片不会再次提交，以免重复收费。</p>@endif
  @endif
 @else<p class="small">尚无当前素材的GPT Image 2费用确认。旧Nano Banana批次已停用，记录保留；查看新计费方案不会生成图片或扣费。</p>@endif
 @if($automatic&&$imageRun?->status==='awaiting_batch_review')<label class="form-check small mt-3"><input type="checkbox" class="form-check-input" data-image-batch-consent>我已查看整套素材，确认采用并保存到素材库；这不授权视频制作。</label>@endif
 @if($automatic&&($imageUsage['pause_reason']??'')==='budget'&&$imageRun?->status==='needs_attention')<label class="small d-block mt-3">提高预算停止线（US$）<input type="number" min="0.60" max="100" step="0.01" value="{{ number_format($imageUsage['auto_review']['spend_stop_usd']+1,2,'.','') }}" class="form-control form-control-sm" data-image-budget></label><label class="form-check small mt-2"><input type="checkbox" class="form-check-input" data-image-resume-consent>我授权提高停止线后继续当前批次，修正次数上限不变。</label>@endif
 @if(!$imagesReviewed&&!$imageActive&&(!$imageFresh||$imageRun?->status==='completed'))<div class="small mt-3"><label class="form-check"><input type="checkbox" class="form-check-input" data-image-auto checked>自动视觉质检与定向修正（每帧最多2轮）</label><label class="d-block mt-2">本批预算停止线（US$）<input type="number" class="form-control form-control-sm" min="0.60" max="100" step="0.01" value="2.00" data-image-budget></label><p class="mt-2">查看费用方案不会调用AI。单次调用可能跨过停止线；在费用页确认后才开始生成及质检。</p></div>@endif
 @if($automatic&&in_array($imageRun?->status,['needs_attention','awaiting_batch_review'])&&!($imageUsage['unknown_cost']??false))<details class="small mt-3"><summary>停止此批，重新准备</summary><p>保留全部图片、质检与费用记录；重新生成需要再次确认费用。</p><label class="form-check"><input type="checkbox" class="form-check-input" data-image-abandon-consent>我确认停止此批，暂不采用。</label><button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-image-op="abandon">停止此批并保留记录</button></details>@endif
 <div class="alert alert-danger small mt-2" data-image-error role="alert" hidden></div>
 <p class="small mt-2 mb-0" data-image-status role="status"></p>
 @if(!$imagesReviewed&&!$imageActive&&(!$imageFresh||in_array($imageRun->status,['completed','needs_attention'])))<label class="small mt-3 d-block">本批调整要求（可选）<textarea class="form-control form-control-sm" maxlength="1000" data-image-feedback placeholder="例如：保留商品外观，背景更简洁。"></textarea></label>@endif
</div>
