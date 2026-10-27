@once<link rel="stylesheet" href="{{ asset('marketing-studio/video-settings.css') }}?v={{ filemtime(public_path('marketing-studio/video-settings.css')) }}">@endonce
<details class="vs-export-details"><summary>查看导出信息</summary><p class="ws-note mt-2">仅展示此成片保存的制作信息；修改当前草稿不会改写本版成片。</p>
@php($exportClips=\App\Models\MarketingVideoClip::where('project_id',$project->id)->whereIn('id',array_filter(array_column($film->input['scenes']??[],'clip_id')))->get(['id','input'])->keyBy('id'))
@forelse($film->input['scenes']??[] as $exportRow)
@php($exportClip=$exportClips[$exportRow['clip_id']??'']??null)
<div class="vs-export-row"><strong>第{{ $loop->iteration }}段 · {{ number_format($exportRow['seconds']??(($exportRow['frames']??0)/24),2) }}秒</strong>
@if($exportClip)@include('marketing.workspace.video-setting-tags',['settingScene'=>$exportClip->input,'settingsClickable'=>false])
@else<div class="video-setting-tags"><span class="setting-tag"><i class="bi bi-volume-up" aria-hidden="true"></i>{{ array_key_exists('with_audio',$exportRow)?($exportRow['with_audio']?'有声音':'无声音'):'声音信息未保存' }}</span><span class="setting-tag"><i class="bi bi-badge-cc" aria-hidden="true"></i>{{ array_key_exists('subtitle',$exportRow)?(trim($exportRow['subtitle'])!==''?'有字幕':'无字幕'):'字幕信息未保存' }}</span></div><small>此历史版本未保存更多分镜设定。</small>@endif</div>
@empty<p class="ws-note">此历史版本未保存逐段导出信息。</p>@endforelse
</details>
