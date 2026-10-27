@php
$navigationStyle=$navigationStyle??'items';
$studioNavActive=$studioNavActive??(request()->routeIs('marketing.home')?'overview':(request()->routeIs('marketing.preview.*')?str_replace('marketing.preview.','',request()->route()->getName()):'content'));
if($studioNavActive==='templates')$studioNavActive='content';
$studioNavigation=[['overview','grid-fill','Overview',route('marketing.home')],['campaigns','flag','Campaigns',route('marketing.preview.campaigns')],['posts','file-earmark-text','Posts',route('marketing.preview.posts')],['calendar','calendar3','Calendar',route('marketing.preview.calendar')],['analytics','bar-chart-line','Analytics',route('marketing.preview.analytics')],['content','collection-play','Content Library',route('marketing.preview.content')],['channels','share','Channels',route('marketing.preview.channels')]];
$studioChildren=[['videos','Video projects',route('marketing.index')],['assets','Media assets',route('marketing.assets')],['templates','Video templates',route('marketing.preview.templates')]];
@endphp
@foreach($studioNavigation as [$navId,$navIcon,$navLabel,$navUrl])
@if($navigationStyle==='items')<li class="sidebar-menu-item">@endif
<a data-studio-nav="{{ $navId }}" class="{{ $navigationStyle==='items'?'sidebar-menu-link':'' }} {{ $studioNavActive===$navId?($navigationStyle==='asset'?'selected':'active'):'' }}" href="{{ $navUrl }}" @if($studioNavActive===$navId)aria-current="page"@endif><i class="bi bi-{{ $navIcon }}" aria-hidden="true"></i><span>{{ $navLabel }}</span></a>
@if($navId==='content' && $studioNavActive==='content')<div class="studio-nav-sub">@foreach($studioChildren as [$childId,$childLabel,$childUrl])<a class="{{ request()->url()===$childUrl?'active':'' }}" href="{{ $childUrl }}">{{ $childLabel }}</a>@endforeach</div>@endif
@if($navigationStyle==='items')</li>@endif
@endforeach
@if($navigationStyle==='items')<li class="sidebar-menu-item mt-4">@else<hr>@endif
<a class="{{ $navigationStyle==='items'?'sidebar-menu-link':'' }}" href="{{ url('/admin') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Return to JW Ecom</span></a>
@if($navigationStyle==='items')</li>@endif
