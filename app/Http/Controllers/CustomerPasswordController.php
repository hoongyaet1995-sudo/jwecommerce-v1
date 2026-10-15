<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
final class CustomerPasswordController extends Controller
{
 public function update(Request $request): RedirectResponse
 {
  $data=$request->validate(['current_password'=>['required','current_password:customer'],'password'=>['required','string','min:8','max:255','confirmed','different:current_password']]);
  $user=$request->user('customer');
  $user->forceFill(['password'=>$data['password'],'remember_token'=>Str::random(60)])->save();
  $request->session()->regenerate();
  return redirect()->to(route('customer.account').'#profile')->with('status','Password updated.');
 }
}
