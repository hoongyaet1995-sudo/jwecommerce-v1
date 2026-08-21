<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View { return view('admin.account', ['owner' => $request->user()]); }
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'current_password' => ['required', 'string'], 'password' => ['nullable', 'string', 'min:12', 'confirmed']]);
        if (! Hash::check($data['current_password'], $request->user()->password)) throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        $request->user()->update(array_filter(['name' => $data['name'], 'password' => $data['password']], fn ($value) => $value !== null));
        return redirect()->route('admin.account.edit');
    }
}
