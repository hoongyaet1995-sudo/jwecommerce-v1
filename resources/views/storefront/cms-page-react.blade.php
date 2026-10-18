@php($pageShellDesign = $previewShellDesign ?? \App\Domain\Cms\ShellDesign::resolve(app(\App\Services\StorefrontShellSettingsService::class)->live(), $payload['page']['document']['shellDesign'] ?? null))
<x-layouts.app :title="$title" :description="$description" :shell-design="$pageShellDesign" :shell-content-width="$shellContentWidth ?? null">
    @if($entry)
        <x-slot:head>
            <meta name="csrf-token" content="{{ csrf_token() }}">
            @foreach($entry['css'] as $css)
                <link rel="stylesheet" href="{{ asset('build-admin/'.$css) }}">
            @endforeach
            @foreach($entry['imports'] as $import)
                <link rel="modulepreload" href="{{ asset('build-admin/'.$import) }}">
            @endforeach
        </x-slot:head>
    @endif

    <div class="min-h-screen bg-neutral-950 text-neutral-100">
        <x-storefront.nav :design="$pageShellDesign" active="landing" :presentation="$previewHeaderPresentation ?? \App\Domain\Cms\HeaderPresentation::resolve($payload['page']['document'], app(\App\Services\StorefrontShellSettingsService::class)->live())" />
        <main>
            @if($entry)
                <script id="cms-page-runtime" type="application/json">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
                <div id="cms-page-root" data-cms-page-header-overlay="{{ \App\Domain\Cms\HeaderPresentation::resolve($payload['page']['document'], app(\App\Services\StorefrontShellSettingsService::class)->live())['mode'] !== 'normal' ? 'true' : 'false' }}">{!! $serverHtml ?? '' !!}</div>
                <noscript><style>[data-motion-enabled]{opacity:1!important;transform:none!important}</style></noscript>
            @else
                <p data-cms-page-assets-unavailable role="alert">Storefront page assets are unavailable.</p>
            @endif
        </main>
        <x-storefront.footer :design="$pageShellDesign" />
        @foreach($quickAddProducts ?? [] as $quickAddProduct)
            <x-storefront.quick-add-variant-modal :product="$quickAddProduct" :variants="$quickAddProduct->variants" :images="$quickAddProduct->images" />
        @endforeach
    </div>

    @if($entry)
        <script type="module" src="{{ asset('build-admin/'.$entry['file']) }}"></script>
    @endif
</x-layouts.app>
