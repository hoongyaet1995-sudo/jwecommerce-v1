@php($pageMessages=app(\App\Services\WebsiteThemeCustomization::class)->content('universal',$contentPage??null))
@if($pageMessages)<aside class="uv-page-messages" aria-label="Brand messages">@foreach($pageMessages as $slot=>$text)<p data-page-message="{{ $slot }}">{{ $text }}</p>@endforeach</aside>@endif
