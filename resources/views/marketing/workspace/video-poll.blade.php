@php($pollTargets=$clipWorkbench['pending'])
@if($clipWorkbench['film_pending'])@php($pollTargets[]=['kind'=>'films','id'=>$clipWorkbench['film']->id])@endif
@if(count($pollTargets))
<div hidden data-workspace-video-poll data-url="{{ route('marketing.workspace.video.status',$project->id) }}" data-targets="{{ json_encode($pollTargets) }}" data-labels="{{ json_encode($clipWorkbench['labels']) }}"></div>
<script src="{{ asset('marketing-studio/workspace-video.js') }}?v={{ filemtime(public_path('marketing-studio/workspace-video.js')) }}" defer></script>
@endif
