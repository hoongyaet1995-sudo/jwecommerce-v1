@php($typeLabels=['promotion'=>'Promotion / Sale','carousel'=>'Carousel / Swipable post','poster'=>'Poster','infographic'=>'Infographic','meme'=>'Meme','real-photo'=>'UG / Real Photo','storytelling'=>'Storytelling','education'=>'Educational / Case Study','checklist'=>'Checklist / Cheat Sheet'])
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4 automation-page-heading">
 <div><div class="eyebrow">MARKETING STUDIO / AUTOMATION</div><h1 class="page-title">AI Post Generation</h1><p class="page-subtitle mb-0">Manage your AI post automations.</p></div>
 <div class="d-flex align-items-center gap-2 flex-wrap"><a class="btn-crm-secondary" href="{{ url('/admin/marketing/posts') }}">View Post</a><a class="btn-crm-secondary" href="{{ $screen==='list'?route('marketing.automation.index'):route('marketing.automation.ai-posts') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $screen==='list'?'Back to Automation':'All AI Post Automations' }}</a></div>
</div>
@if($screen==='presets')
@php($presetIcons=['blank'=>'pencil-square','spotlight'=>'box-seam','offers'=>'tag','education'=>'book','guide'=>'signpost-split','checklist'=>'list-check','moments'=>'emoji-smile','story'=>'journal-text','case'=>'clipboard-data'])
<section class="studio-card"><h2 class="h5 fw-bold">Choose a Starting Point</h2><p class="text-muted mb-4">Each preset creates one independent automation. You can adjust every setting.</p><div class="row g-3">
@foreach($presets as $key=>$preset)
 <div class="col-12 col-md-6 col-xl-4"><a class="automation-preset d-block border rounded-4 p-4 h-100 text-decoration-none text-dark" href="{{ route('marketing.automation.new',['preset'=>$key]) }}"><i class="bi bi-{{ $presetIcons[$key]??'file-earmark-text' }} fs-4" aria-hidden="true"></i><h3 class="h6 fw-bold mt-3">{{ $preset['name'] }}</h3><p class="small text-muted mb-0">{{ $preset['purpose'] }} <span aria-hidden="true">→</span></p></a></div>
@endforeach
</div></section>
@include('marketing.automation.intelligence-overview')
@elseif($screen==='list')
<section class="studio-card"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4"><div><h2 class="h5 fw-bold">AI Post Automations</h2><p class="text-muted mb-0">Save a direction and enable its schedule when ready. New automations are paused by default.</p></div><a class="btn-crm-primary" href="{{ route('marketing.automation.create') }}">+ Create automation</a></div>
@if($automations->isEmpty())<div class="text-center py-5"><i class="bi bi-diagram-3 fs-1 text-muted"></i><h3 class="h5 mt-3">No automations yet</h3><p class="text-muted">Start with a preset or create your own direction.</p></div>
@else<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Automation</th><th>Post type</th><th>Sources</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($automations as $a)<tr><td><strong>{{ $a->name }}</strong><div class="small text-muted">{{ $presets[$a->preset]['name']??'Custom' }}</div></td><td>{{ $typeLabels[$a->settings['type']] }}</td><td>@include('marketing.automation.source-range-summary',['sourceSettings'=>$a->settings])</td><td><span class="badge text-bg-light">{{ $a->schedule_enabled?'Active schedule':'Paused' }}</span></td><td><a class="btn-crm-secondary" href="{{ url('/admin/marketing/automation/ai-posts/'.$a->id.'/edit') }}">Configure →</a></td></tr>@endforeach
</tbody></table></div>@endif</section>
@else
@include('marketing.automation.editor')
@endif
<style>.automation-preset:hover{background:#f3f7e7;border-color:#607e21!important}</style>
