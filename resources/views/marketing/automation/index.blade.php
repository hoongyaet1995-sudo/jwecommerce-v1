<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>{{ $settings?'AI Post Generation':'Automation' }} | Marketing Studio</title>
@foreach(['bootstrap.min','bootstrap-icons','main','xenibox','studio','navigation','controls'] as $style)
<link rel="stylesheet" href="{{ asset('marketing-studio/'.$style.'.css') }}?v={{ filemtime(public_path('marketing-studio/'.$style.'.css')) }}">
@endforeach
@if(($screen??'')==='editor')
<link rel="stylesheet" href="{{ asset('marketing-ai-brief-preview/brief.css') }}?v={{ filemtime(public_path('marketing-ai-brief-preview/brief.css')) }}">
<link rel="stylesheet" href="{{ asset('marketing-ai-brief-preview2/free.css') }}?v={{ filemtime(public_path('marketing-ai-brief-preview2/free.css')) }}">
<link rel="stylesheet" href="{{ asset('marketing-studio/automation.css') }}?v={{ filemtime(public_path('marketing-studio/automation.css')) }}">
@endif
<link rel="stylesheet" href="{{ asset('marketing-studio/automation-intelligence.css') }}?v={{ filemtime(public_path('marketing-studio/automation-intelligence.css')) }}">@if(($screen??'')==='editor')<link rel="stylesheet" href="{{ asset('marketing-studio/source-pool.css') }}?v={{ filemtime(public_path('marketing-studio/source-pool.css')) }}">@endif</head><body class="{{ ($screen??'')==='editor'?'marketing-free-post marketing-automation-post':'' }}">
@include('marketing.navigation.chrome',['freePostChrome'=>true])
<div class="page-header"><div><div class="eyebrow">MARKETING STUDIO @if($settings) / AUTOMATION @endif</div><h1 class="page-title">{{ $settings?'AI Post Generation':'Automation' }}</h1><p class="page-subtitle">{{ $settings?'Manage your AI post automations.':'Prepare your next marketing workflow, in one place.' }}</p></div></div>
<main class="crm-page-content">
@if(!$settings)
<div class="row g-4"><div class="col-12 col-md-8 col-xl-5"><article class="studio-card h-100">
<div class="d-flex align-items-start gap-3"><div class="rounded-3 p-3 bg-light"><i class="bi bi-stars fs-4" aria-hidden="true"></i></div><div><h2 class="h5 fw-bold mb-2">AI Post Generation</h2><p class="text-muted mb-0">Turn your products and brand into social posts, ready for review.</p></div></div>
<ul class="list-unstyled my-4"><li class="mb-2"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Use your product catalog and brand</li><li class="mb-2"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Prepare images and platform copy</li><li><i class="bi bi-check2 me-2" aria-hidden="true"></i>Review drafts before publishing</li></ul>
<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap"><a class="btn-crm-secondary" href="{{ route('marketing.automation.ai-posts') }}">Configure <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a><span class="badge rounded-pill text-bg-light px-3 py-2">Not configured</span></div>
</article></div></div>
@else
@include('marketing.automation.workspace')
@endif
</main>
<footer class="footer-custom"><span>JW Marketing Studio &copy; {{ date('Y') }}</span><span class="text-muted small">Nothing is generated or published automatically.</span></footer></div>
<script src="{{ asset('marketing-studio/bootstrap.bundle.min.js') }}" defer></script><script src="{{ asset('marketing-studio/studio.js') }}" defer></script>
<script src="{{ asset('marketing-studio/campaign-navigation.js') }}" defer></script><script src="{{ asset('marketing-studio/audience-navigation.js') }}" data-audience-visible="{{ \App\Domain\Marketing\MarketingPermission::allows(auth()->user(),\App\Domain\Marketing\MarketingPermission::VIEW_AUDIENCE)?'1':'0' }}" defer></script>
</body></html>
