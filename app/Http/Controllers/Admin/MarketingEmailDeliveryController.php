<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Marketing\MarketingPermission as P;
use App\Services\Marketing\MarketingEmailDelivery;
class MarketingEmailDeliveryController extends Controller {
 public function status(Request $r,MarketingEmailDelivery $s,string $id){abort_unless(P::allows($r->user(),P::VIEW),403);return response()->json(['deliveries'=>$s->listing($r->user()->id,$id),'enabled'=>(bool)config('marketing.email_test_enabled'),'store_url'=>url('/'),'sender_address'=>config('mail.from.address'),'sender_name'=>config('mail.from.name')])->header('Cache-Control','private,no-store');}
 public function send(Request $r,MarketingEmailDelivery $s,string $id){abort_unless(P::allows($r->user(),P::MANAGE_CAMPAIGNS),403);$d=$r->validate(['version'=>'required|integer|min:1','recipient'=>'required|email:rfc|max:254','landing_url'=>'required|url|max:2000','confirmed'=>'required|accepted']);$v=$s->prepare($r->user()->id,$id,$d);return response()->json($v,$v['reused']?200:201);}
}
