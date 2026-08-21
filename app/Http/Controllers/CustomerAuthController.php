<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function create(): View
    {
        return view('storefront.auth', ['mode' => 'login']);
    }

    public function registerForm(): View
    {
        return view('storefront.auth', ['mode' => 'register']);
    }

    public function forgotPasswordForm(): View
    {
        return view('storefront.auth', ['mode' => 'forgot']);
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $customer = Customer::query()->where('email', $data['email'])->whereHas('user', fn ($query) => $query->where('is_admin', false))->exists();

        if ($customer) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return back()->with('status', 'If this member email is registered, a reset link has been sent.');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::query()->where('email', $credentials['email'])->where('is_admin', false)->first();

        if (! $user || ! $user->customer || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'The provided customer credentials do not match our records.'])->onlyInput('email');
        }

        Auth::guard('customer')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/account');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ]);
        [$firstName, $lastName] = array_pad(explode(' ', $data['name'], 2), 2, null);
        $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_admin' => false]);
        Customer::query()->create(['user_id' => $user->id, 'email' => $user->email, 'first_name' => $firstName, 'last_name' => $lastName]);
        Auth::guard('customer')->login($user);
        $request->session()->regenerate();

        return redirect('/account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }
}
