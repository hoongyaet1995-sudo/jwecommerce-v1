  <form data-mw-reference-form method="post" enctype="multipart/form-data" action="{{ route('marketing.workspace.material.reference',[$project->id,$need['id']]) }}">
   @csrf<input type="hidden" name="version" value="{{ $project->version }}"><input type="hidden" name="request_id" value="{{ (string) Str::uuid() }}">
   @if($need['kind']==='factual_detail')
    <label class="small d-block mb-1" for="answer-{{ $need['id'] }}">确认真实资料</label><textarea class="form-control form-control-sm" id="answer-{{ $need['id'] }}" name="answer" maxlength="2000" rows="2" required>{{ $need['reference']['value']??'' }}</textarea>
   @else
    @php
     $saved=$need['reference']??null;
     $selected=$saved&&$saved['type']==='catalog_image'?'catalog:'.$saved['index']:($saved&&$saved['type']==='project_asset'?'upload:'.$saved['id']:'');
    @endphp
    <fieldset class="mw-reference-choices"><legend class="small">选择已有参考图，或上传新的参考</legend>
     @foreach($project->snapshot['images']??[] as $catalog)
      <label class="mw-reference-option"><span class="mr-radio"><input type="radio" name="source" value="catalog:{{ $loop->index }}" @checked($selected==='catalog:'.$loop->index)> 使用已有商品参考图{{ count($project->snapshot['images'])>1?' '.$loop->iteration:'' }}</span><img src="{{ asset(ltrim($catalog['path'],'/')) }}" alt="商品目录图 {{ $loop->iteration }}" loading="lazy"></label>
     @endforeach
     @foreach($referenceAssets as $reference)
      <label class="mw-reference-option"><span class="mr-radio"><input type="radio" name="source" value="upload:{{ $reference->id }}" @checked($selected==='upload:'.$reference->id)> {{ $reference->name }}</span><img src="{{ route('marketing.asset',[$project->id,$reference->id]) }}" alt="{{ $reference->name }}" loading="lazy"></label>
     @endforeach
    </fieldset>
    <label class="mr-upload mw-reference-upload mt-3" for="image-{{ $need['id'] }}" data-mw-drop>
     <i class="bi bi-cloud-arrow-up"></i><span>选择参考图，或拖放到此处</span><small>JPG、PNG、WebP，最大10MB · 仅作参考</small>
     <input type="file" class="mw-file-input" id="image-{{ $need['id'] }}" name="image" accept="image/jpeg,image/png,image/webp" aria-label="上传{{ $need['name'] }}（仅作参考）">
    </label>
    <div data-mw-file-preview hidden class="mt-2"><img class="mw-reference-preview" alt="待保存的上传参考图"><div class="d-flex align-items-center justify-content-between mt-2"><small data-mw-file-name></small><button type="button" class="btn btn-sm btn-light" data-mw-file-clear>移除新上传图片</button></div></div>
    <p class="small text-secondary mt-2" data-mw-reference-status role="status">{{ $selected?'已载入保存的参考；更换后需重新保存。':'请选择上方已有参考，或上传一张新图片。' }}</p>
   @endif
   <label class="form-check my-2 small"><input type="checkbox" class="form-check-input" name="permission" value="1" required><span>我确认资料真实，并有权将其用于本项目的AI素材制备。</span></label>
   <button class="btn btn-sm ws-primary" @disabled(!$fresh)>保存这项参考</button>
  </form>
