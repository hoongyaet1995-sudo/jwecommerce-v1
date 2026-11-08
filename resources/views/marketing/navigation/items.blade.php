@php
$navigationStyle=$navigationStyle??'items';
$studioNavActive=$studioNavActive??(request()->routeIs('marketing.home')?'overview':(request()->routeIs('marketing.preview.*')?str_replace('marketing.preview.','',request()->route()->getName()):'content'));
if($studioNavActive==='campaign-templates')$studioNavActive='campaigns';
if(in_array($studioNavActive,['templates','videos']))$studioNavActive='video-studio';
if(request()->routeIs('marketing.avatars*','marketing.settings.*'))$studioNavActive='settings';
if(request()->routeIs('marketing.library.*','marketing.preview.content'))$studioNavActive='content';
if(request()->routeIs('marketing.index','marketing.show','marketing.create','marketing.templates.*','marketing.preview.templates'))$studioNavActive='video-studio';
$studioNavigation=[['overview','grid-fill','Overview',route('marketing.home')],['campaigns','flag','Campaigns',route('marketing.preview.campaigns')],['automation','diagram-3','Automation',route('marketing.automation.index')],['calendar','calendar3','Calendar',route('marketing.preview.calendar')],['analytics','bar-chart-line','Analytics',route('marketing.preview.analytics')],['content','collection-play','Content Library',route('marketing.preview.content')],['video-studio','camera-video','Video Studio',route('marketing.video-studio')],['channels','share','Channels',route('marketing.preview.channels')]];
$studioChildren=match($studioNavActive){'content'=>[['images','Image Asset',route('marketing.library.images')],['videos','Video Asset',route('marketing.library.videos')]],'video-studio'=>[['videos','Video projects',route('marketing.index')],['templates','Video templates',route('marketing.templates.entry')]],default=>[]};
@endphp
@foreach($studioNavigation as [$navId,$navIcon,$navLabel,$navUrl])
@if($navigationStyle==='items')<li class="sidebar-menu-item">@endif
<a data-studio-nav="{{ $navId }}" class="{{ $navigationStyle==='items'?'sidebar-menu-link':'' }} {{ $studioNavActive===$navId?($navigationStyle==='asset'?'selected':'active'):'' }}" href="{{ $navUrl }}" @if($studioNavActive===$navId)aria-current="page"@endif><i class="bi bi-{{ $navIcon }}" aria-hidden="true"></i><span>{{ $navLabel }}</span></a>
@if($navId===$studioNavActive && count($studioChildren))<div class="studio-nav-sub">@foreach($studioChildren as [$childId,$childLabel,$childUrl])<a class="{{ request()->url()===$childUrl?'active':'' }}" href="{{ $childUrl }}">{{ $childLabel }}</a>@endforeach</div>@endif
@if($navigationStyle==='items')</li>@endif
@endforeach
@if($navigationStyle==='items')<li class="sidebar-menu-item" data-settings-group="1"><a class="sidebar-menu-link {{ $studioNavActive==='settings'?'active':'' }}" href="{{ route('marketing.settings.index') }}"><i class="bi bi-gear"></i><span>Settings</span></a></li>@else<a class="{{ $studioNavActive==='settings'?'selected':'' }}" href="{{ route('marketing.settings.index') }}"><i class="bi bi-gear"></i><span>Settings</span></a>@endif
@if($studioNavActive==='settings')<div class="studio-nav-sub"><a class="{{ request()->routeIs('marketing.settings.email')?'active':'' }}" href="{{ route('marketing.settings.email') }}">Email</a><a class="{{ request()->routeIs('marketing.avatars*')?'active':'' }}" href="{{ route('marketing.avatars') }}">Avatar</a></div>@endif
@if($navigationStyle==='items')<li class="sidebar-menu-item mt-4">@else<hr>@endif
<a class="{{ $navigationStyle==='items'?'sidebar-menu-link':'' }}" href="{{ url('/admin') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Return to JW Ecom</span></a>
@if($navigationStyle==='items')</li>@endif
