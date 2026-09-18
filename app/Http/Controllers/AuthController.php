<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(Request $request): View
    {
        if ($request->routeIs('admin.login') && $this->safeAdminIntendedPath($request->query('intended'))) {
            $request->session()->put('url.intended', url($request->query('intended')));
        }

        return view('auth.login', [
            'loginAction' => $request->routeIs('admin.login')
                ? route('admin.login.store')
                : route('login.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_admin' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }

    private function safeAdminIntendedPath(mixed $intended): ?string
    {
        if (! is_string($intended)) {
            return null;
        }

        $parts = parse_url($intended);

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'])
            || ($path !== '/admin' && ! str_starts_with($path, '/admin/'))) {
            return null;
        }

        return $intended;
    }
}
