@php($package=isset($revision->document['material_package'])?app(\App\Services\Marketing\MarketingMaterialPackage::class)->inspectReferences($project,$revision->document['material_package']):null)
@php($referenceAssets=$project->assets()->where('authorized',true)->where('mime','like','image/%')->get())
<section class="mt-4" id="material-package">
 <h3>参考资料与生产素材包</h3>
 <p class="small text-secondary">仅向你收集无法可靠推断的真实资料；虚拟人物、虚构场景由AI准备。原图仅作参考，生产图片还需制备与审阅。</p>
 @if(!$package)
 <div class="ws-note mb-3">先整理各段实际需要的参考资料，再准备首帧、尾帧与补充参考图。</div>
 <button type="button" class="btn ws-primary w-100" data-creative-action="material_plan">整理素材需求与生产素材包（文字 AI） <i class="bi bi-arrow-right"></i></button>
 @else
 @php($fresh=$package['source_digest']===app(\App\Services\Marketing\MarketingMaterialPackage::class)->digest($project,$revision))
 <p class="small">{{ $package['summary'] }}</p>
 @if(!$fresh)<div class="alert alert-warning">来源已变化，此素材包仅供查看。请重新整理，不要沿用旧素材批准。</div>@endif
 <h4 class="fs-6 mt-3">你需要补充的真实资料</h4>
 @forelse(array_filter($package['requirements'],fn($item)=>$item['owner']==='employee') as $need)
 <details class="border rounded-3 p-3 mb-2" @if($need['reference']===null) open @endif>
  <summary><strong>{{ $need['name'] }}</strong> <span class="badge bg-light text-dark">{{ $need['reference']?'参考已提供':(($need['status']??'')==='reference_invalid'?'参考已失效，请重新提供':($need['required']?'待补充':'可选')) }}</span></summary>
  <p class="small mt-2 mb-1">用于第{{ implode('、',$need['scene_numbers']) }}段 · {{ $need['reason'] }}</p>
  <p class="small mb-2">{{ $need['question'] }}</p>
  @if($need['reference'])<p class="small text-success">已记录参考（{{ $need['reference']['type']==='confirmed_fact'?'文字资料':($need['reference']['type']==='catalog_image'?'商品目录图':'项目上传图') }}），仍须制备生产图。@if($need['reference']['type']==='confirmed_fact') {{ $need['reference']['value'] }} @endif</p>@endif
  <form method="post" enctype="multipart/form-data" action="{{ route('marketing.workspace.material.reference',[$project->id,$need['id']]) }}">
   @csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="request_id" value="{{ (string) Str::uuid() }}">
   @if($need['kind']==='factual_detail')
    <label class="small d-block mb-1" for="answer-{{ $need['id'] }}">确认真实资料</label><textarea class="form-control form-control-sm" id="answer-{{ $need['id'] }}" name="answer" maxlength="2000" rows="2" required>{{ $need['reference']['value']??'' }}</textarea>
   @else
    <label class="small d-block mb-1" for="source-{{ $need['id'] }}">选择现有参考图</label>
    <select class="form-select form-select-sm" id="source-{{ $need['id'] }}" name="source"><option value="">选择参考，或在下面上传</option>@foreach($project->snapshot['images']??[] as $catalog)<option value="catalog:{{ $loop->index }}">商品目录图 {{ $loop->iteration }}</option>@endforeach @foreach($referenceAssets as $reference)<option value="upload:{{ $reference->id }}">{{ $reference->name }}</option>@endforeach</select>
    <label class="small d-block mt-2 mb-1" for="image-{{ $need['id'] }}">上传{{ $need['name'] }}（仅作参考）</label><input type="file" class="form-control form-control-sm" id="image-{{ $need['id'] }}" name="image" accept="image/jpeg,image/png,image/webp"><small class="text-secondary">JPG、PNG、WebP，最大10MB；有上传文件时使用该文件。</small>
   @endif
   <label class="form-check my-2 small"><input type="checkbox" class="form-check-input" name="permission" value="1" required><span>我确认资料真实，并有权将其用于本项目的AI素材制备。</span></label>
   <button class="btn btn-sm ws-primary" @disabled(!$fresh)>保存这项参考</button>
  </form>
 </details>
 @empty<p class="ws-note">当前计划没有需要员工补充的真实资料。</p>@endforelse
 <details class="border rounded-3 p-3 my-3"><summary class="fw-semibold">AI负责准备的内容</summary>@forelse(array_filter($package['requirements'],fn($item)=>$item['owner']==='ai') as $need)<div class="small mt-3"><strong>{{ $need['name'] }} · 第{{ implode('、',$need['scene_numbers']) }}段</strong><p class="mb-1">{{ $need['preparation'] }}</p><span class="text-secondary">{{ $need['kind']==='graphic'?'排版内容，无需上传图片':'待AI制备，无需员工上传' }}</span></div>@empty<p class="small mt-2">没有额外AI参考需求；仍需制备每段生产图片。</p>@endforelse</details>
 <h4 class="fs-6">生产素材包 <span class="badge bg-light text-dark">{{ count($package['outputs']) }}个规划位置</span></h4>
 <p class="small text-secondary">下面是待制备位置，尚未生成图片。是否提交给视频API，之后按所选模式核实。</p>
 @foreach(collect($package['outputs'])->groupBy('scene_number') as $sceneNumber=>$outputs)
 <details class="border rounded-3 p-3 mb-2"><summary>第{{ $sceneNumber }}段 · 首帧／尾帧／补充参考</summary>@foreach($outputs as $output)<div class="small mt-3"><strong>{{ ['start_frame'=>'开场关键帧','end_frame'=>'结束关键帧','extra_image'=>'补充参考图'][$output['role']] }}</strong> <span class="badge bg-light text-dark">待制备</span><p class="mt-1 mb-0">{{ $output['preparation'] }}</p></div>@endforeach</details>
 @endforeach
 <div class="ws-note my-3">{{ $package['status']==='awaiting_inputs'?'先补齐上方必要真实资料，再核算图片制备费用。':'参考准备阶段已完成，下一步需要图片制备报价与授权。' }} 当前尚无图片报价或生成授权。</div>
 <button class="btn ws-primary w-100" type="button" disabled>图片制备待报价确认</button>
 <button type="button" class="btn btn-outline-secondary w-100 mt-2" data-creative-action="material_plan">重新整理素材计划（文字 AI）</button>
 @endif
</section>
