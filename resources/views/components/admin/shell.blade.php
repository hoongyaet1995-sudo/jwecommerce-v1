@php($user = auth()->user())

<x-layouts.app :title="$title.' | JW Studio Admin'">
    <div data-admin-shell data-admin-overlay-coordinator class="min-h-screen bg-neutral-950 font-sans text-neutral-100">
        <x-admin.sidebar :active="$active" />
        <button type="button" data-admin-sidebar-backdrop aria-label="Close navigation overlay" class="fixed inset-0 z-30 hidden bg-black/60 backdrop-blur-sm lg:hidden"></button>

        <main data-admin-main class="min-h-screen lg:pl-72">
            <header class="sticky top-0 z-20 flex min-h-16 items-center justify-between gap-4 border-b border-neutral-800 bg-neutral-900/90 px-5 py-3 backdrop-blur lg:px-8">
                @if($search)
                    <form action="{{ route('admin.search') }}" class="flex min-w-0 flex-1 items-center gap-3">
                        <label class="sr-only" for="admin-search">Search products, menus, orders, customers</label>
                        <input id="admin-search" name="q" autofocus value="{{ $query }}" placeholder="Type a command, search products, menus, orders, customers..." class="min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-neutral-500">
                        <kbd class="rounded border border-neutral-700 bg-neutral-950 px-2 py-1 font-mono text-[10px] text-neutral-400">ESC</kbd>
                    </form>
                @else
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">{{ $eyebrow }}</p>
                        <h1 class="mt-1 truncate text-sm font-bold text-white">{{ $title }}</h1>
                    </div>
                @endif

                <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                    @if($actionHref && $actionLabel)
                        <a href="{{ $actionHref }}" class="hidden rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200 md:block">{{ $actionLabel }}</a>
                    @endif
                    <button type="button" data-admin-command-open aria-haspopup="dialog" aria-controls="admin-command-dialog" class="rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200">Search <span class="hidden font-mono text-[10px] text-neutral-500 sm:inline">⌘K</span></button>
                    <button type="button" data-admin-notification-toggle aria-haspopup="dialog" aria-controls="admin-notification-drawer" aria-expanded="false" class="relative rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200">Alerts @if($adminShellNotifications->isNotEmpty())<span class="ml-1 inline-flex min-w-4 justify-center rounded-full bg-indigo-500 px-1 text-[10px] text-white">{{ $adminShellNotifications->count() }}</span>@endif</button>
                    <button type="button" data-admin-sidebar-collapse-toggle aria-pressed="false" class="hidden rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200 lg:block"><span data-admin-sidebar-collapse-label>Collapse nav</span></button>
                    <button type="button" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" class="rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200 lg:hidden">Menu</button>
                    <button type="button" data-admin-theme-toggle data-admin-theme-storage="jw_admin_theme" aria-pressed="false" class="rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200">Theme</button>
                    <details data-admin-profile-menu class="relative">
                        <summary data-admin-profile-toggle class="cursor-pointer list-none rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white">{{ $user->name }}</summary>
                        <div class="absolute right-0 z-30 mt-2 w-48 rounded-2xl border border-neutral-700 bg-neutral-900 p-2 shadow-2xl">
                            <p class="px-3 py-2 text-[11px] text-neutral-400">{{ $user->email }}</p>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" data-admin-logout class="w-full rounded-xl px-3 py-2 text-left text-xs font-semibold text-rose-300 hover:bg-rose-500/10">Log out</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            {{ $slot }}
        </main>

        <div id="admin-command-dialog" data-admin-command-dialog data-admin-overlay-surface role="dialog" aria-modal="true" aria-labelledby="admin-command-title" aria-hidden="true" class="fixed inset-0 z-50 hidden bg-black/70 p-4 backdrop-blur-sm sm:p-10">
            <button type="button" data-admin-command-close aria-label="Close command search" aria-hidden="true" tabindex="-1" class="absolute inset-0 cursor-default"></button>
            <section data-admin-overlay-content tabindex="-1" class="relative mx-auto max-w-2xl rounded-3xl border border-neutral-700 bg-neutral-900 p-5 shadow-2xl">
                <div class="flex items-center justify-between gap-4"><div><p class="text-[10px] font-bold uppercase tracking-wider text-indigo-300">Admin command</p><h2 id="admin-command-title" class="mt-1 text-lg font-black text-white">Search live records</h2></div><button type="button" data-admin-command-close class="rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200">Close</button></div>
                <form action="{{ route('admin.search') }}" method="GET" class="mt-5">
                    <label class="sr-only" for="admin-command-input">Search products, orders, customers, and pages</label>
                    <input id="admin-command-input" data-admin-command-input name="q" type="search" placeholder="Search products, orders, customers, pages..." class="w-full rounded-2xl border border-neutral-700 bg-neutral-950 px-4 py-3 text-sm text-white outline-none placeholder:text-neutral-500 focus:border-indigo-400">
                    <p class="mt-3 text-xs text-neutral-400">Results are searched on the server. Press Escape to close.</p>
                </form>
            </section>
        </div>

        <aside id="admin-notification-drawer" data-admin-notification-drawer data-admin-overlay-surface role="dialog" aria-modal="true" aria-labelledby="admin-notification-title" aria-hidden="true" class="fixed inset-y-0 right-0 z-50 hidden w-full max-w-md border-l border-neutral-700 bg-neutral-900 p-5 shadow-2xl">
            <div data-admin-overlay-content tabindex="-1" class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-bold uppercase tracking-wider text-indigo-300">Live activity</p><h2 id="admin-notification-title" class="mt-1 text-lg font-black text-white">Notifications</h2></div><button type="button" data-admin-notification-close class="rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200">Close</button></div>
            <div class="mt-6 space-y-3">
                @forelse($adminShellNotifications as $notification)
                    <a href="{{ $notification['url'] }}" class="block rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4 transition hover:border-indigo-500/60"><div class="flex items-start gap-3"><span class="rounded-lg bg-indigo-500/15 px-2 py-1 text-[10px] font-bold text-indigo-300">{{ $notification['icon'] }}</span><div class="min-w-0 flex-1"><p class="text-xs font-bold text-white">{{ $notification['title'] }}</p><p class="mt-1 text-[11px] text-neutral-400">{{ $notification['description'] }}</p><p class="mt-2 text-[10px] text-neutral-500">{{ $notification['time'] }}</p></div></div></a>
                @empty
                    <p class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4 text-xs text-neutral-400">No recent order, inventory, or customer activity.</p>
                @endforelse
            </div>
        </aside>

        @isset($overlay)
            {{ $overlay }}
        @endisset
    </div>
</x-layouts.app>
