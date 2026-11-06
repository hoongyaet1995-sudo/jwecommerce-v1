<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<base href="/marketing-ai-brief-preview2/"><title>Create post with AI | Marketing Studio</title>
@foreach(['bootstrap.min','bootstrap-icons','main','xenibox','studio','navigation','controls'] as $style)
<link rel="stylesheet" href="{{ asset('marketing-studio/'.$style.'.css') }}?v={{ filemtime(public_path('marketing-studio/'.$style.'.css')) }}">
@endforeach
<link rel="stylesheet" href="{{ asset('marketing-ai-brief-preview/brief.css') }}">
<link rel="stylesheet" href="{{ asset('marketing-ai-brief-preview2/free.css') }}?v={{ filemtime(public_path('marketing-ai-brief-preview2/free.css')) }}">
</head><body class="marketing-free-post">
@include('marketing.navigation.chrome',['freePostChrome'=>true])
<div class="page-header"><div><div class="eyebrow"><a href="{{ url('/admin/marketing/posts') }}">POSTS</a> / CREATE WITH AI</div><h1 class="page-title">Create post with AI</h1><p class="page-subtitle">Choose your products, set a creative direction and generate drafts for review.</p></div><span class="design-label">AI Post Generator</span></div>
<main class="crm-page-content">@include('marketing.posts.free-post-form')</main>
<footer class="footer-custom"><span>JW Marketing Studio</span><span class="text-muted small">Drafts for review · Nothing is published automatically</span></footer></div>
<script src="{{ asset('marketing-studio/bootstrap.bundle.min.js') }}" defer></script>
<script src="{{ asset('marketing-studio/studio.js') }}" defer></script>
<script src="{{ asset('marketing-studio/campaign-navigation.js') }}" defer></script>
<script src="{{ asset('marketing-studio/audience-navigation.js') }}" data-audience-visible="{{ \App\Domain\Marketing\MarketingPermission::allows(auth()->user(),\App\Domain\Marketing\MarketingPermission::VIEW_AUDIENCE)?'1':'0' }}" defer></script>
<script src="{{ asset('marketing-ai-brief-preview2/free-data.js') }}?v={{ filemtime(public_path('marketing-ai-brief-preview2/free-data.js')) }}"></script>
<script src="{{ asset('marketing-ai-brief-preview2/free.js') }}?v={{ filemtime(public_path('marketing-ai-brief-preview2/free.js')) }}"></script>
<script>window.FreePostBoot={!! json_encode($boot,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};</script>
<script src="{{ asset('marketing-ai-brief-preview2/free-live-core.js') }}?v={{ filemtime(public_path('marketing-ai-brief-preview2/free-live-core.js')) }}"></script>
<script src="{{ asset('marketing-ai-brief-preview2/free-live.js') }}?v={{ filemtime(public_path('marketing-ai-brief-preview2/free-live.js')) }}"></script>
</body></html>
