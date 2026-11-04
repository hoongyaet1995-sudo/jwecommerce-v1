<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class RewardsCheckoutController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user('customer')?->customer, 403);
        $data = $request->validate(['use_rewards' => 'required|boolean']);
        $request->session()->put('use_rewards', (bool) $data['use_rewards']);
        return redirect()->route('checkout.create')->withInput($request->only([
            'first_name','last_name','email','phone','line_1','line_2','city','state',
            'postal_code','country','selected_address_id','payment_method','bank_account_id',
        ]));
    }
}
