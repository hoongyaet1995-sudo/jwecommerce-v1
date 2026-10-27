<div class="ws-preference-bar"><i class="bi bi-file-earmark-text"></i><span>{{ $preferences['language']??'语言待确认' }} · {{ implode(' · ',$preferences['platforms']??[]) }} · {{ ($preferences['subtitles']??false)?'字幕开启':'字幕关闭' }}</span><a href="{{ $link('preferences') }}">修改偏好 <i class="bi bi-pencil-square"></i></a></div>
<div class="ws-split"><section class="ws-card ws-chat"><div class="ws-assistant-heading mb-3"><span class="ws-ai-avatar">AI</span><div><h2 class="mb-0">AI策划助手</h2><small class="text-secondary">同一项目 · 连续对话</small></div></div>
<div class="mt-auto">@include('marketing.workspace.composer')</div>
<div class="ws-suggestions my-3"><button type="button" data-chat-quick="请结合这个项目资料，建议一个合适的营销目标和受众，并解释理由。">请AI建议</button></div>

<p class="ws-note mt-2">每轮对话会同步右侧摘要草稿；确认整体资料后再进入创意。</p></section><section class="ws-card">@include('marketing.workspace.summary')@include('marketing.workspace.planning-confirmation-action')</section></div>
