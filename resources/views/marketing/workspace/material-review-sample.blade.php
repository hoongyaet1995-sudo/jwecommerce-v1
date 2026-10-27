@php
$sampleShots=$revision?->document['director_plan']['shots']??[];
$sampleTitle=$project?->concepts[$project?->selected_concept??0]['title']??'商品介绍视频';
@endphp
<link rel="stylesheet" href="{{ asset('marketing-studio/material-review-sample.css') }}">
<div class="mr-sample" data-material-review-sample>
 <div class="mr-sample-notice"><i class="bi bi-bezier2"></i><span>交互样本 · 项目内容只读，参考需求为示例。所有操作仅影响本页，不调用AI、不上传或保存。</span></div>
 <div class="row g-3">
  <div class="col-lg-7"><section class="ws-card h-100">
   <div class="d-flex justify-content-between gap-3 align-items-start"><div><span class="mr-eyebrow">选定的故事</span><h2 class="mt-2">{{ $sampleTitle }}</h2><p class="text-secondary small">{{ $summary['平台与时长']??'15秒 · 竖屏' }} · {{ $brief['language']??'华语' }}</p></div>
    <div class="dropdown"><button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-label="更多镜头操作"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-mr-edit>提出镜头调整</button></li><li><button class="dropdown-item" type="button" data-mr-redesign>重新设计镜头（演示）</button></li></ul></div>
   </div>
   <div class="mr-story-preview">@if($image)<img src="{{ $image }}" alt="现有商品参考图，非成片">@else<i class="bi bi-film"></i>@endif<span>现有参考 · 非生成成片</span></div>
   <div class="mr-beats">@forelse($sampleShots as $shot)<div><span>{{ $loop->iteration }}</span><div><strong>{{ $shot['start'] }}–{{ $shot['end'] }}秒</strong><small>{{ $shot['voiceover']?:$shot['purpose'] }}</small></div></div>@empty<div><span>1</span><div><strong>先审阅分镜</strong><small>这里将显示已保存的故事段落。</small></div></div>@endforelse</div>
   <p class="small text-secondary mb-0 mt-3">先确认这支视频讲什么、怎么拍，再准备生产图片。</p>
  </section></div>
  <div class="col-lg-5"><section class="ws-card h-100 mr-task-panel">
   <span class="mr-eyebrow">现在先做这一件事</span><h2 class="mt-2" data-mr-current-title>审阅镜头与口播</h2><p class="text-secondary" data-mr-current-copy>按段看清画面与表达，确认后再检查需要哪些参考资料。</p>
   <ol class="mr-checklist"><li data-mr-check="0" class="current"><span>1</span><div><strong>审阅镜头与口播</strong><small>逐段查看导演设计</small></div><i class="bi bi-arrow-right"></i></li><li data-mr-check="1"><span>2</span><div><strong>补充必要真实参考</strong><small>其余虚拟内容由AI准备</small></div><i class="bi bi-lock"></i></li><li data-mr-check="2"><span>3</span><div><strong>核对素材制备计划</strong><small>先看素材与费用安排，再生成</small></div><i class="bi bi-lock"></i></li></ol>
   <div class="mt-auto pt-3"><button type="button" class="btn ws-primary w-100" data-mr-start>开始审阅与准备 <i class="bi bi-arrow-right"></i></button><p class="small text-secondary text-center mt-2 mb-0">图片制备与视频制作分别确认费用</p></div>
  </section></div>
 </div>
 <details class="ws-card mt-3 mr-question"><summary><i class="bi bi-chat-dots me-2"></i>有疑问？向项目AI助手提问</summary><p class="small text-secondary mt-3 mb-2">示例：“需要老板照片吗？”“为什么要补背面照片？”</p><div class="input-group"><input class="form-control" aria-label="样本问题" placeholder="说说你的疑问…"><button class="btn btn-outline-secondary" type="button" data-mr-question>发送（演示）</button></div><p class="small mt-3 mb-0" data-mr-answer hidden></p></details>
 <div class="modal fade" id="materialReviewSample" tabindex="-1" aria-labelledby="materialReviewSampleTitle"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content rounded-4">
  <div class="modal-header border-0"><div><span class="mr-eyebrow">分镜与素材 · 准备向导</span><h2 class="modal-title fs-4 mt-1" id="materialReviewSampleTitle">{{ $sampleTitle }}</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭准备向导"></button></div>
  <div class="mr-modal-progress" aria-label="样本准备进度"><span data-mr-stage-label="0" class="active">1 审阅分镜</span><i class="bi bi-chevron-right"></i><span data-mr-stage-label="1">2 真实参考</span><i class="bi bi-chevron-right"></i><span data-mr-stage-label="2">3 制备计划</span></div>
  <div class="modal-body">
   <section data-mr-stage="0"><div class="mr-step-intro"><h3>先确认：这是不是你想讲的故事？</h3><p>每次只看一段。细节可展开查看，确认后再进入素材准备。</p></div>
    <div class="mr-scene-tabs" role="group" aria-label="选择故事段落">@forelse($sampleShots as $shot)<button type="button" class="btn btn-sm {{ $loop->first?'ws-primary':'btn-light' }}" data-mr-scene-go="{{ $loop->index }}">第{{ $loop->iteration }}段 · {{ $shot['start'] }}–{{ $shot['end'] }}秒</button>@empty<span>当前项目尚无导演稿；此样本只展示流程结构。</span>@endforelse</div>
    @foreach($sampleShots as $shot)<article data-mr-scene="{{ $loop->index }}" @if(!$loop->first) hidden @endif><div class="mr-scene-brief"><span class="mr-eyebrow">这一段要传达什么</span><p class="fw-semibold my-2">{{ $shot['purpose'] }}</p><div class="mr-speech"><i class="bi bi-mic"></i><p class="mb-0">{{ $shot['voiceover']?:'无口播' }}</p></div></div>
     <div class="mr-detail-grid">@foreach(['subject'=>'人物与调度','framing'=>'构图与机位','motion'=>'运镜与节奏','lighting'=>'光线与背景','continuity'=>'连续性','beats'=>'内部动作节拍','voiceover'=>'口播','subtitle'=>'字幕','checks'=>'验收重点'] as $key=>$label)<details><summary>{{ $label }}</summary><div class="small">@if($key==='beats')@foreach($shot[$key] as $beat)<p>{{ $beat['start'] }}–{{ $beat['end'] }}秒 · {{ $beat['action'] }}</p>@endforeach @elseif($key==='checks')@foreach($shot[$key] as $check)<p>{{ $check }}</p>@endforeach @else<p>{{ $shot[$key]?:'无' }}</p>@endif</div></details>@endforeach</div>
    </article>@endforeach
    <div class="mr-adjust" data-mr-adjust hidden><label class="fw-semibold small" for="mr-adjustment">你想调整什么？</label><textarea class="form-control mt-2" id="mr-adjustment" rows="2" placeholder="例如：开场直接讲用途，语气亲切一点…"></textarea><small class="text-secondary">演示：文字只保留在本页，尚未请求AI重新设计。</small></div>
   </section>
   <section data-mr-stage="1" hidden><div class="mr-step-intro"><h3>只补AI不能可靠推断的真实资料</h3><p>以下为交互示例，不是AI对当前JTG项目的新判定。实际只展示分镜确实需要的资料。</p></div>
    <div class="mr-reference-grid"><div class="mr-reference-card"><div class="d-flex justify-content-between"><strong>商品正面参考</strong><span class="badge bg-light text-dark">示例 · 可复用</span></div><p class="small text-secondary mt-2">使用已有商品图，不用重新上传。</p><label class="mr-radio"><input type="radio" name="mr-front-source" checked> 使用已有商品参考图</label>@if($image)<img src="{{ $image }}" alt="示例：复用已有商品参考">@endif</div>
    <div class="mr-reference-card"><div class="d-flex justify-content-between"><strong>商品另一面参考</strong><span class="badge bg-light text-dark">示例需求</span></div><p class="small text-secondary mt-2">只有镜头要展示另一面时才索取。系统不能凭空编造真实结构。</p><label class="mr-upload" for="mr-reference-image"><i class="bi bi-cloud-arrow-up"></i><span data-mr-file-label>选择参考图，或拖放到此处</span><small>仅在本页预览，不上传服务器</small></label><input type="file" id="mr-reference-image" accept="image/jpeg,image/png,image/webp" class="visually-hidden"><img data-mr-file-preview alt="本地示例参考图" hidden><label class="small mt-2"><input type="checkbox" data-mr-no-rear checked> 此故事不展示另一面，暂不需要</label></div></div>
    <div class="mr-ai-work"><i class="bi bi-stars"></i><div><strong>AI负责：虚构场景、虚拟人物、首尾构图与补充参考</strong><p class="small mb-0 mt-1">你提供真实依据，AI负责制备适合制作的画面。原图不会直接提交视频API。</p></div></div>
   </section>
   <section data-mr-stage="2" hidden><div class="mr-step-intro"><h3>看看接下来会准备哪些画面</h3><p>这里先确认制备范围。以下为规划位置，尚未生成图片。</p></div>
    <div class="mr-output-grid">@foreach(['开场关键帧'=>'bi-play-circle','结束关键帧'=>'bi-stop-circle','补充参考图'=>'bi-images'] as $name=>$icon)<div class="mr-output-card"><div><i class="bi {{ $icon }}"></i><span>待AI制备</span></div><strong>{{ $name }}</strong><p>{{ ['开场关键帧'=>'确定这一镜的主体、场景与开始构图。','结束关键帧'=>'延续同一主体，表达动作落点与结束状态。','补充参考图'=>'固定人物、商品或场景的一致性，可跨镜头复用。'][$name] }}</p></div>@endforeach</div>
    <div class="mr-price-note"><div><strong>下一步：确认图片制备费用</strong><p class="small mt-1 mb-0">数量和费用核算后再授权。此样本不显示虚构报价，也不会生成媒体。</p></div><span class="badge bg-light text-dark">待核算</span></div>
    <details class="mr-handoff"><summary>图片准备好之后会怎样？</summary><p class="small mt-2 mb-0">逐张审阅／修改／批准 → 检查分镜与素材 → 进入 Step 4 确认视频制作费用。尚未生成图片时，不把本步骤标为制作就绪。</p></details>
   </section>
   <p class="small text-danger mt-3" data-mr-error hidden>请先选择参考图，或确认本故事不展示另一面。</p>
  </div>
  <div class="modal-footer justify-content-between"><button type="button" class="btn btn-outline-secondary" data-mr-back disabled><i class="bi bi-arrow-left"></i> 上一步</button><span class="small text-secondary d-none d-md-inline">样本操作 · 不保存项目</span><button type="button" class="btn ws-primary" data-mr-next>确认镜头，检查素材 <i class="bi bi-arrow-right"></i></button></div>
 </div></div></div>
</div>
<script src="{{ asset('marketing-studio/material-review-sample.js') }}" defer></script>
