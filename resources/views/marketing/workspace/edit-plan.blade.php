@php($ep=$m['edit_plan'])
<div class="ws-chat-proposal ws-edit-plan"><strong>{{ $ep['status']==='accepted'?'已采用的剪辑方案':($ep['status']==='pending'?'待采用的剪辑方案':'历史剪辑方案') }} · {{ number_format($ep['duration'],2) }}秒</strong>
@foreach($ep['scenes'] as $cut)<p>第{{ $loop->iteration }}段 · {{ number_format($cut['in_frame']/24,2) }}–{{ number_format($cut['out_frame']/24,2) }}秒 / 原{{ number_format($cut['source_frames']/24,1) }}秒<br>{{ preg_replace('/(?:依据\s*)?(?:evidence_id\s*)?[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}/iu','根据本段素材分析',$cut['reason']) }}</p>@endforeach
<p class="small text-secondary">仅同步裁剪现有音画；人物、背景、台词与原声保持。采用后本地生成新预览，原版保留。</p>
@if($ep['status']==='pending')
<button type="button" class="btn ws-primary" data-edit-adopt="{{ $ep['id'] }}" data-url="{{ $ep['adopt_url'] }}" data-base="{{ $ep['base_film_id'] }}" data-hash="{{ $ep['base_sha256'] }}" @disabled(($clipWorkbench['film']?->id??null)!==$ep['base_film_id'])>采用方案，生成新预览</button>
<button type="button" class="btn btn-light" data-edit-reject="{{ $ep['id'] }}" data-url="{{ $ep['reject_url'] }}">保留当前版本</button>
@if(($clipWorkbench['film']?->id??null)!==$ep['base_film_id'])<a class="d-block small mt-2" href="{{ route('marketing.workspace.show',['project'=>$project->id,'screen'=>'finished','film'=>$ep['base_film_id']]) }}">切回此方案对应的成片</a>@endif
@elseif($ep['film_id'])<a class="btn btn-sm btn-light" href="{{ route('marketing.workspace.show',['project'=>$project->id,'screen'=>'finished','film'=>$ep['film_id']]) }}">查看此方案的新预览</a>
@else<small>{{ ['rejected'=>'已保留当前版本','superseded'=>'已被更新的方案替代','stale'=>'来源已变化'][$ep['status']]??'方案已处理' }}</small>@endif</div>
