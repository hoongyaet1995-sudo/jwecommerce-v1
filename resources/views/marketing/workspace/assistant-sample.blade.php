<link rel="stylesheet" href="{{ asset('marketing-studio/assistant-sample.css') }}?v={{ filemtime(public_path('marketing-studio/assistant-sample.css')) }}">
<div data-assistant-sample data-mode="{{ $screen }}">
<div class="ws-sample-banner"><span><i class="bi bi-bezier2"></i> 对话交互样本 <span class="text-secondary ms-2">JTG 示例 · 预设回复 · 不写入项目、不收费</span></span><nav class="d-flex gap-3"><a href="{{ $link('guided') }}">Step 1 单一对话</a><a href="{{ $link('concepts') }}">共享抽屉</a></nav></div>
@if($screen==='guided')
<div class="ws-preference-bar"><i class="bi bi-file-earmark-text"></i><span>商品展示 · 华语 · Facebook · 字幕开启 · AI推荐声线</span><span class="ms-auto text-secondary">准备资料已带入</span></div>
<div class="ws-split ws-sample-layout"><div class="ws-card ws-sample-chat-card">@include('marketing.workspace.assistant-sample-chat')</div>
<section class="ws-card"><div class="d-flex align-items-center justify-content-between"><h2 class="mb-0">策划摘要</h2><span class="ws-badge" data-sample-progress>已整理 3/7</span></div><p class="text-secondary mt-2">聊天内容先理解，再整理；建议不会直接成为确认资料。</p>
<dl class="ws-summary">
@foreach(['offer'=>['产品','20ft General Purpose Container','已带入'],'audience'=>['受众','一起确认适合的主要受众','待补充'],'goal'=>['目的','希望观众看完后做什么？','待补充'],'platform'=>['平台与时长','Facebook · 时长由AI建议','已带入'],'pain'=>['核心痛点','客户需要解决什么问题？','待补充'],'proof'=>['特点与依据','只使用商家核实的特点','待补充'],'tone'=>['调性','亲切专业','已带入']] as $key=>$row)
<div><dt>{{ $row[0] }}</dt><dd><span data-sample-field="{{ $key }}">{{ $row[1] }}</span><span class="ws-badge ms-1 {{ $row[2]==='待补充'?'ws-pending':'' }}" data-sample-status="{{ $key }}">{{ $row[2] }}</span></dd></div>
@endforeach
</dl><div class="ws-tip"><i class="bi bi-shield-check"></i><span>这份摘要只确认策划资料，制作预算和发布仍需分别授权。</span></div>
<details class="mt-3"><summary>查看前面提供的制作偏好</summary><p class="text-secondary mt-2">华语（马来西亚） · 字幕开启 · AI推荐声线 · 背景音乐稍后决定。此处为样本资料，不修改原项目。</p></details></section></div>
@else
<h2 class="ws-hero-title">选一个值得讲的故事</h2><p class="ws-subtitle">样本：在当前创意页面继续同一项目对话。</p>
<div class="ws-context mb-3"><i class="bi bi-chat"></i> 获得询价 <span>|</span><i class="bi bi-people"></i> 第一次选购的客户 <span>|</span><i class="bi bi-facebook facebook"></i> Facebook</div>
<div class="ws-concepts">
@foreach([['先有需求，再看货柜','场景故事','从客户的储物需求切入，介绍货柜的适用场景。','设备和货物，下一步放哪里？'],['和你聊聊货柜选择','人物口播','用亲切专业的语言，介绍选择货柜时要考虑的用途。','货柜怎么选？先聊聊你的用途。'],['从外到内，看懂货柜','商品展示','通过外观和内部空间，介绍已确认的商品特点。','打开柜门，看看里面的空间。']] as $i=>$c)
<section class="ws-card ws-concept {{ $i===1?'recommended':'' }}"><h2>{{ $c[0] }}</h2><p class="text-secondary">{{ $c[1] }} · 示例方案</p><div class="ws-sample-cover">@if($image)<img src="{{ $image }}" alt="已保存商品图片，仅作样本示意">@else<i class="bi bi-box-seam"></i>@endif<small>商品素材示意 · 非生成封面</small></div><p class="mt-3"><span class="ws-badge">HOOK</span> {{ $c[3] }}</p><hr><h3>视觉规划</h3><p @if($i===1) data-sample-field="creative" @endif>{{ $c[2] }}</p><span class="ws-badge mb-3" @if($i===1) data-sample-status="creative" @endif>待选择</span><button type="button" class="btn ws-primary mt-auto" data-sample-open>和AI讨论这个方向 <i class="bi bi-arrow-right"></i></button></section>
@endforeach
</div><div class="ws-sample-launch ws-card"><i class="bi bi-chat-dots"></i><span>不满意第二个故事？可以直接说“像老板和客户聊天”。</span><button type="button" class="btn ws-primary ms-auto" data-sample-open>继续项目对话</button></div>
<div class="offcanvas offcanvas-end ws-conversation-drawer ws-sample-drawer" tabindex="-1" id="assistantSampleDrawer" aria-labelledby="assistantSampleDrawerTitle"><div class="offcanvas-header"><div><h2 class="fs-5 mb-1" id="assistantSampleDrawerTitle">项目对话</h2><small class="text-secondary">JTG 示例 · 当前阶段：创意方向</small></div><button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="关闭样本对话"></button></div><div class="offcanvas-body">@include('marketing.workspace.assistant-sample-chat')</div></div>
@endif
</div>
<script src="{{ asset('marketing-studio/assistant-sample.js') }}?v={{ filemtime(public_path('marketing-studio/assistant-sample.js')) }}" defer></script>
