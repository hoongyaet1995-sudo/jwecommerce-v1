@php($pageMessages=app(\App\Services\WebsiteThemeCustomization::class)->content('fashion',$contentPage??null))
@if($pageMessages)<aside class="fs-page-messages" aria-label="Brand messages">@foreach($pageMessages as $slot=>$text)<p data-page-message="{{ $slot }}">{{ $text }}</p>@endforeach</aside>@endif
