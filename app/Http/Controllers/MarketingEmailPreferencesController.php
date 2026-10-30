<?php
namespace App\Http\Controllers;
use App\Services\Marketing\MarketingEmailUnsubscribe;
use Illuminate\Http\Request;
final class MarketingEmailPreferencesController extends Controller {
 public function show(Request $r,string $delivery){app(MarketingEmailUnsubscribe::class)->resolve($delivery,(string)$r->query('store'));return $this->page(false);}
 public function update(Request $r,string $delivery){app(MarketingEmailUnsubscribe::class)->apply($delivery,(string)$r->query('store'));return $this->page(true);}
 private function page(bool $success){return response()->view('marketing.email.preferences',compact('success'))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
}
