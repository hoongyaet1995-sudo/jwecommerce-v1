<x-layouts.app title="Admin Portal Login">
    <main class="relative isolate flex min-h-screen items-center justify-center overflow-hidden bg-neutral-950 p-4 text-white">
        <div aria-hidden="true" class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(79,70,229,.18),transparent_23%),radial-gradient(circle_at_78%_28%,rgba(124,58,237,.15),transparent_21%),radial-gradient(circle_at_78%_76%,rgba(16,185,129,.10),transparent_20%)]"></div>
        <div aria-hidden="true" class="absolute inset-0 bg-black/75 backdrop-blur-md"></div>

        <section id="admin-login-modal" aria-labelledby="admin-login-title" class="relative w-full max-w-md overflow-hidden rounded-3xl border border-neutral-800 bg-neutral-900 p-6 shadow-2xl sm:p-8">
            <header class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-cyan-600 via-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-500/25">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h1 id="admin-login-title" class="text-xl font-bold tracking-tight text-white">Admin Portal Login</h1>
                <p class="mt-1 text-xs text-neutral-400">Sign in to access Universal Ecommerce administration.</p>
            </header>

            <form method="POST" action="{{ $loginAction }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold text-neutral-300">Admin Work Email</label>
                    <div class="relative">
                        <svg viewBox="0 0 24 24" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="18" height="14" x="3" y="5" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="admin@auroracommerce.io" class="w-full rounded-xl border border-neutral-700 bg-neutral-800/80 py-2.5 pl-10 pr-3.5 text-xs text-white outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    @error('email')<p class="mt-1.5 text-[11px] font-medium text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <div class="mb-1.5 flex items-center justify-between"><label for="password" class="block text-xs font-semibold text-neutral-300">Password</label><span class="cursor-default text-[11px] text-indigo-400">Forgot?</span></div>
                    <div class="relative">
                        <svg viewBox="0 0 24 24" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-neutral-700 bg-neutral-800/80 py-2.5 pl-10 pr-3.5 text-xs text-white outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="target-role" class="mb-1.5 block text-xs font-semibold text-neutral-300">Target Role</label>
                    <select id="target-role" class="w-full rounded-xl border border-neutral-700 bg-neutral-800/80 px-3.5 py-2.5 text-xs text-white outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"><option>Super Administrator (Full Privileges)</option><option>Catalog &amp; Navigation Manager</option><option>Order Fulfillment Specialist</option><option>Developer (API &amp; Specs Access)</option></select>
                </div>
                <div class="flex items-center justify-between py-1">
                    <label class="flex cursor-pointer items-center gap-2"><input name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-neutral-600 bg-neutral-800 text-indigo-600 focus:ring-indigo-500"><span class="text-xs text-neutral-400">Remember this workstation</span></label>
                    <span class="flex items-center gap-1 text-[10px] font-bold uppercase text-emerald-400"><svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>Secure Laravel session</span>
                </div>
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-700"><span>Sign in to Admin</span><svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
            </form>

            <footer class="mt-6 border-t border-neutral-800 pt-5">
                <p class="mb-2 text-center text-[11px] font-semibold uppercase text-neutral-400">One-Click Demo Personas</p>
                <div class="grid grid-cols-2 gap-2"><div class="rounded-xl border border-neutral-700/80 p-2 text-left"><div class="text-xs font-bold text-white">Alexander S.</div><div class="mt-0.5 text-[10px] font-medium text-indigo-400">Super Admin</div></div><div class="rounded-xl border border-neutral-700/80 p-2 text-left"><div class="text-xs font-bold text-white">Maya Lin</div><div class="mt-0.5 text-[10px] font-medium text-cyan-400">Catalog &amp; Menu Mgr</div></div></div>
            </footer>
        </section>
    </main>
</x-layouts.app>
