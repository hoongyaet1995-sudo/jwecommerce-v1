<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Domain\Marketing\MarketingPermission;
use App\Services\Marketing\MarketingChannels;
use Illuminate\Http\Request;
class MarketingChannelsController extends Controller {
 private function access(Request $r,bool $manage=false):void{abort_unless(MarketingPermission::allows($r->user())&&(!$manage||$r->user()->is_super_admin),403);}
 public function index(Request $r,MarketingChannels $s,?string $platform=null){$this->access($r);return response()->view('marketing.navigation.preview',['studioNavActive'=>'channels','navigationBoot'=>MarketingNavigationController::boot('channels')+['channelsPlatform'=>$platform,'channels'=>$s->listing($r),'csrf'=>csrf_token()]])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
 public function authorize(Request $r,string $platform,MarketingChannels $s){$this->access($r,true);abort_unless($s->ready($r,$platform)['ready'],409,'Platform app or Social Hub callback configuration is not ready. Review the settings.');$r->session()->put($platform.'_marketing_oauth',['actor'=>$r->user()->id,'platform'=>$platform,'expires'=>now()->addMinutes(10)->timestamp]);if(\App\Services\Marketing\ExternalChannelConnections::supports($platform))return app(\App\Services\Marketing\ExternalChannelConnections::class)->authorize($r,$platform);return app($platform==='facebook'?FacebookAccountController::class:InstagramAccountController::class)->authorize($r);}
 public function hubReturn(Request $r,string $platform,\App\Services\Marketing\ExternalChannelConnections $s){$this->access($r,true);return $s->hubReturn($r,$platform);}
 public function select(Request $r,string $platform,MarketingChannels $s){$this->access($r,true);$d=$r->validate(['account_id'=>'required|string|regex:/^[A-Za-z0-9_-]{1,100}$/D']);$s->select($r,$platform,$d['account_id']);return response()->json($s->listing($r))->header('Cache-Control','no-store');}
 public function cancel(Request $r,string $platform,MarketingChannels $s){$this->access($r,true);if($s->pending($r,$platform))$r->session()->forget([$platform.'_pending',$platform.'_marketing_oauth']);return response()->json($s->listing($r))->header('Cache-Control','no-store');}
 public function check(Request $r,string $platform,string $connection,MarketingChannels $s){$this->access($r,true);$s->check($s->owned($platform,$connection));return response()->json($s->listing($r))->header('Cache-Control','no-store');}
 public function disconnect(Request $r,string $platform,string $connection,MarketingChannels $s){$this->access($r,true);$s->disconnect($s->owned($platform,$connection));return response()->json($s->listing($r))->header('Cache-Control','no-store');}
}
