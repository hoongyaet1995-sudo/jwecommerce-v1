@props(['design' => null])
@php
    $design = $design ?? \App\Domain\Cms\ShellDesign::resolve(app(\App\Services\StorefrontShellSettingsService::class)->live());
    $footerCategories = \App\Models\Category::query()->where('is_active', true)->orderBy('name')->limit(5)->get();
    $footerProduct = \App\Models\Product::query()->where('status', 'active')->orderBy('id')->first();
    $footerMenu = \App\Models\Menu::query()->where('key', 'footer')->with([
        'rootItems' => fn ($query) => $query->where('is_active', true),
        'rootItems.children' => fn ($query) => $query->where('is_active', true),
    ])->first();
    $footerSections = $footerMenu?->rootItems?->where('is_active', true)->values() ?? collect();
    $footerGroups = $footerSections->isNotEmpty() ? $footerSections->chunk((int) ceil($footerSections->count() / 2)) : collect();
    $badgeStyle = fn (?string $color) => \App\Support\MenuBadgePalette::inlineStyle($color);
    if(request()->attributes->has('websiteThemeDemoVariants')) {
        $design=array_replace(\App\Domain\Cms\ShellDesign::defaults(),['tagline'=>'Good food. Brighter days.']);
        $footerSections=collect(['Shop'=>['All products','Pantry staples'],'Collections'=>['Everyday essentials','Cooking ingredients'],'About'=>['Our story','Contact','FAQ']])->map(function($links,$label){
            $section=new \App\Models\MenuItem(['label'=>$label]);
            return $section->setRelation('children',collect($links)->map(fn($link)=>new \App\Models\MenuItem(['label'=>$link,'url'=>'#','is_active'=>true])));
        });
    }
@endphp

@if($design)
@include('components.storefront.designed-footer')
@else
<footer class="border-t border-zinc-200 bg-zinc-100 font-sans text-zinc-600 transition-colors duration-200 dark:border-zinc-900 dark:bg-zinc-950 dark:text-zinc-400">
    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 py-12 sm:px-6 md:grid-cols-5 lg:px-8">
        <section class="space-y-4 md:col-span-2">
            <a href="{{ route('storefront.home') }}" aria-label="{{ $shopBrandName }} home" class="inline-flex rounded-lg transition hover:opacity-80"><img src="{{ asset('images/source/jwstudio_logo_1786766390670.jpg') }}" alt="{{ $shopBrandName }} Logo" class="h-8 w-8 rounded-lg object-cover shadow ring-1 ring-zinc-200 dark:ring-zinc-800"></a>
            <p class="max-w-sm text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">Precision-crafted studio audio systems, ergonomic desk architectures, mechanical timepieces, and everyday carry essentials for intentional living.</p>
        </section>
        @if($footerGroups->isNotEmpty())
        @foreach($footerGroups as $groupIndex => $group)
        <nav aria-label="Footer Navigation {{ $groupIndex + 1 }}">
            @foreach($group as $section)
            <div @class(['mt-6' => ! $loop->first])>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-900 dark:text-zinc-200">{{ $section->label }}</h2>
            <ul class="space-y-2 text-xs">@foreach($section->children->where('is_active', true) as $link)<li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ $link->url }}" @if($link->is_external) target="_blank" rel="noopener" @endif>{{ $link->label }}@if($link->badge)<em class="ml-1 rounded border px-1.5 py-0.5 text-[9px] not-italic uppercase" style="{{ $badgeStyle($link->badge_color) }}">{{ $link->badge }}</em>@endif</a></li>@endforeach</ul>
            </div>
            @endforeach
        </nav>
        @endforeach
        @endif
    </div>
    <div class="mx-auto flex max-w-7xl flex-col gap-4 border-t border-zinc-200 px-4 py-6 text-xs sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8 dark:border-zinc-900"><span>© 2026 {{ $shopBrandName }}. All rights reserved.</span><span class="font-mono">Privacy Policy&nbsp;&nbsp;•&nbsp;&nbsp;Terms of Service&nbsp;&nbsp;•&nbsp;&nbsp;Global Compliance</span></div>
</footer>

@endif
