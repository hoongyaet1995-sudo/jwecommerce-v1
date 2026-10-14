<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function create(): View
    {
        return view(app(\App\Services\WebsiteDesignService::class)->view('auth'), ['mode' => 'login']);
    }

    public function registerForm(): View
    {
        return view(app(\App\Services\WebsiteDesignService::class)->view('auth'), ['mode' => 'register']);
    }

    public function forgotPasswordForm(): View
    {
        return view(app(\App\Services\WebsiteDesignService::class)->view('auth'), ['mode' => 'forgot']);
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

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view(app(\App\Services\WebsiteDesignService::class)->view('reset'), ['mode'=>'reset','token'=>$token,'email'=>$request->query('email','')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data=$request->validate(['email'=>['required','email'],'token'=>['required','string'],'password'=>['required','string','min:8','confirmed']]);
        $eligible=User::where('email',$data['email'])->where('is_admin',false)->whereHas('customer')->exists();
        if (!$eligible) return back()->withErrors(['email'=>'This reset link is invalid or has expired.']);
        $status=Password::reset($data,function (User $user,string $password) {
            $user->forceFill(['password'=>Hash::make($password),'remember_token'=>\Illuminate\Support\Str::random(60)])->save();
            event(new \Illuminate\Auth\Events\PasswordReset($user));
        });
        return $status===Password::PASSWORD_RESET
            ? redirect()->route('customer.login')->with('status','Password reset. Please sign in.')
            : back()->withErrors(['email'=>'This reset link is invalid or has expired.']);
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

        // A shared session may contain an Admin intended URL. Customer login
        // always lands in the customer account, never in the Admin portal.
        return redirect()->route('customer.account');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        [$firstName, $lastName] = array_pad(explode(' ', $data['name'], 2), 2, null);
        $user = DB::transaction(function () use ($data, $firstName, $lastName): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_admin' => false,
            ]);
            $customer = Customer::query()
                ->whereRaw('lower(email) = ?', [strtolower($data['email'])])
                ->lockForUpdate()
                ->first();

            if ($customer?->user_id) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already connected to a customer account.',
                ]);
            }

            if ($customer) {
                $customer->update(['user_id' => $user->id, ...(filled($data['phone'] ?? null) ? ['phone' => $data['phone']] : [])]);
            } else {
                $customer = Customer::query()->create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'phone' => $data['phone'] ?? null,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
            }

            app(\App\Services\Customers\CustomerPlatformService::class)->record($customer, 'storefront');
            return $user;
        });
        Auth::guard('customer')->login($user);
        $request->session()->regenerate();

        return redirect('/account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        // Preserve the independently authenticated Admin guard.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }
}
