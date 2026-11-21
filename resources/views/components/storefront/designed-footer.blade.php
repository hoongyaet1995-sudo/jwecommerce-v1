@php
    $compact = ($design['footerLayout'] ?? 'classic') === 'compact';
    $activeGroups = $footerSections->where('is_active', true);
    $legalGroups = $compact ? $activeGroups->filter(fn ($group) => strtolower(trim($group->label)) === 'legal') : collect();
    $navigationGroups = $activeGroups->reject(fn ($group) => $legalGroups->containsStrict($group));
    $footerBrand = trim((string) ($design['brandName'] ?? '')) ?: $shopBrandName;
    $footerTheme = data_get($navigationContext ?? [], 'context.themePalette') ?? app(\App\Services\CmsThemePalette::class)->current()['palette'];
    $footerPageStyle = data_get($navigationContext ?? [], 'page.document.style', []);
    $footerPalette = ($footerPageStyle['colorSource'] ?? '') === 'theme' ? $footerTheme : ($footerPageStyle['palette'] ?? $footerTheme);
    $footerContrast = $footerPalette['contrast'] ?? $footerPalette['ink'] ?? '#0d1b18';
    $footerPaper = $footerPalette['paper'] ?? '#ffffff';
    if (!is_string($footerContrast) || !preg_match('/^#[a-f0-9]{6}$/i', $footerContrast)) $footerContrast = '#0d1b18';
    if (!is_string($footerPaper) || !preg_match('/^#[a-f0-9]{6}$/i', $footerPaper)) $footerPaper = '#ffffff';
    $footerInk = \App\Domain\Cms\ShellDesign::footerForeground($footerContrast);
@endphp
<link rel="stylesheet" href="{{ asset('css/cms-footer-design.css') }}?v={{ filemtime(public_path('css/cms-footer-design.css')) }}">
<div class="cms-footer-region" style="--cms-shell-contrast:{{ $footerContrast }};--cms-shell-oncontrast:{{ $footerInk }};--cms-shell-paper:{{ $footerPaper }}">
<footer data-shell-footer data-shell-appearance="light" data-shell-business="{{ $design['business'] }}" data-shell-style="unified" @if($compact) data-footer-layout="compact" @endif class="jw-shell-footer cms-footer">
    <div class="cms-footer-intro">
        <section><a href="{{ route('storefront.home') }}" class="cms-footer-brand">{{ $footerBrand }}</a>@if(filled($design['tagline']))<p class="cms-footer-description">{{ $design['tagline'] }}</p>@endif @if($compact && filled($design['contactEmail']))<div class="cms-footer-contact"><a href="mailto:{{ $design['contactEmail'] }}">{{ $design['contactEmail'] }}</a></div>@endif</section>
        @if(!$compact && filled($design['contactEmail']))<section class="cms-footer-contact"><h2>Contact</h2><a href="mailto:{{ $design['contactEmail'] }}">{{ $design['contactEmail'] }}</a></section>@endif
    </div>
    @if($navigationGroups->isNotEmpty())
    <div class="cms-footer-navigation">
        @foreach($navigationGroups as $section)
        <nav aria-label="{{ $section->label }}"><h2>{{ $section->label }}</h2>
            @foreach($section->children->where('is_active', true) as $link)
            <a href="{{ $link->url }}" @if($link->is_external) target="_blank" rel="noopener" @endif><span>{{ $link->label }}</span>@if(filled($link->badge))<em class="cms-footer-badge" style="{{ \App\Support\MenuBadgePalette::inlineStyle($link->badge_color) }}">{{ $link->badge }}</em>@endif</a>
            @endforeach
        </nav>
        @endforeach
    </div>
    @endif
    <div class="cms-footer-wordmark" aria-hidden="true">{{ $footerBrand }}</div>
    <div data-privacy-entry class="cms-footer-legal">© {{ date('Y') }} {{ $footerBrand }}. All rights reserved.
        @foreach($legalGroups as $section)
        <nav aria-label="{{ $section->label }}">
            @foreach($section->children->where('is_active', true) as $link)
            <a href="{{ $link->url }}" @if($link->is_external) target="_blank" rel="noopener" @endif><span>{{ $link->label }}</span>@if(filled($link->badge))<em class="cms-footer-badge" style="{{ \App\Support\MenuBadgePalette::inlineStyle($link->badge_color) }}">{{ $link->badge }}</em>@endif</a>
            @endforeach
        </nav>
        @endforeach
    </div>
</footer>
</div>
