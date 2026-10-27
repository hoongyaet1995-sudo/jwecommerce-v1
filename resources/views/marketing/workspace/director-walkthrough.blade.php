<div class="modal fade" id="directorWalkthrough" tabindex="-1" aria-labelledby="directorWalkthroughTitle">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
  <div class="modal-content rounded-4">
   <div class="modal-header border-0 px-4 pt-4">
    <div><p class="text-success small fw-bold mb-1"><i class="bi bi-camera-reels me-2"></i>导演镜头设计</p><h2 class="modal-title fs-4" id="directorWalkthroughTitle">{{ $project->concepts[$project->selected_concept]['title']??'逐段审阅导演稿' }}</h2></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭导演稿"></button>
   </div>
   <div class="modal-body px-4" data-director-walkthrough>
    <details class="border rounded-3 p-3 mb-3 bg-light"><summary class="fw-semibold">全片设计摘要</summary><p class="small mt-2 mb-0">{{ $director['summary'] }}</p>@foreach($director['notes'] as $note)<p class="small text-secondary mt-2 mb-0">{{ $note }}</p>@endforeach</details>
    @foreach($director['shots'] as $shot)
    <section data-director-step="{{ $loop->index }}" @if(!$loop->first) hidden @endif aria-label="第{{ $loop->iteration }}段导演稿">
     <div class="text-center mb-3"><p class="text-success small fw-bold mb-2">STEP {{ $loop->iteration }} OF {{ count($director['shots']) }} · {{ $shot['start'] }}–{{ $shot['end'] }}秒</p><h3 class="fs-5">{{ $shot['purpose'] }}</h3></div>
     @foreach(['subject'=>'人物与调度','framing'=>'构图与机位','motion'=>'运镜与节奏','lighting'=>'光线与背景','continuity'=>'连续性','beats'=>'内部动作节拍','voiceover'=>'口播','subtitle'=>'字幕','checks'=>'验收重点'] as $key=>$label)
     <details class="border rounded-3 p-3 mb-2" name="director-categories-{{ $shot['scene_number'] }}">
      <summary class="fw-semibold">{{ $label }} @if($key==='voiceover' && !$shot[$key])<span class="small text-secondary">· 无口播</span>@elseif($key==='subtitle' && !$shot[$key])<span class="small text-secondary">· 无字幕</span>@endif</summary>
      <div class="small mt-3">
       @if($key==='beats')@foreach($shot[$key] as $beat)<p class="mb-2"><span class="badge bg-light text-dark border me-2">{{ $beat['start'] }}–{{ $beat['end'] }}秒</span>{{ $beat['action'] }}</p>@endforeach
       @elseif($key==='checks')<ul class="mb-0 ps-3">@foreach($shot[$key] as $check)<li class="mb-2">{{ $check }}</li>@endforeach</ul>
       @else<p class="mb-0 text-break">{{ $shot[$key]?:'无' }}</p>@endif
      </div>
     </details>
     @endforeach
    </section>
    @endforeach
   </div>
   <div class="modal-footer d-block px-4 pb-4">
    <nav class="d-flex justify-content-center gap-2 mb-3" aria-label="导演稿段落进度">@foreach($director['shots'] as $shot)<button type="button" class="btn btn-sm rounded-pill {{ $loop->first?'ws-primary':'btn-light' }}" data-director-go="{{ $loop->index }}" aria-label="查看第{{ $loop->iteration }}段" @if($loop->first) aria-current="step" @endif>{{ $loop->iteration }}</button>@endforeach</nav>
    <div class="d-flex justify-content-between align-items-center"><button type="button" class="btn btn-outline-secondary" data-director-prev disabled><i class="bi bi-arrow-left"></i> 上一段</button><span class="small text-secondary" data-director-position aria-live="polite">第1段／共{{ count($director['shots']) }}段</span><button type="button" class="btn ws-primary" data-director-next>下一段 <i class="bi bi-arrow-right"></i></button><button type="button" class="btn ws-primary" data-director-finish data-bs-dismiss="modal" hidden>完成查看 <i class="bi bi-check2"></i></button></div>
   </div>
  </div>
 </div>
</div>
<script src="{{ asset('marketing-studio/director-walkthrough.js') }}?v={{ filemtime(public_path('marketing-studio/director-walkthrough.js')) }}" defer></script>
