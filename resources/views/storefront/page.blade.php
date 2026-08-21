<x-layouts.app :title="$page->title.' | JW Atelier'">
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <x-storefront.nav active="landing" />
        <main>
            @forelse($page->sections as $section)
                <x-storefront.page-section :section="$section" :products="$products" />
            @empty
                <section class="mx-auto max-w-4xl px-5 py-24 text-center"><p class="font-mono text-xs uppercase tracking-widest text-amber-300">Published page</p><h1 class="mt-4 font-serif text-5xl font-black text-white">{{ $page->title }}</h1></section>
            @endforelse
        </main>
        <x-storefront.footer />
    </div>
</x-layouts.app>
