<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Domain\Marketing\MarketingPermission as P;
use App\Services\Marketing\MarketingWhatsAppBatches;
use Illuminate\Http\Request;
final class MarketingWhatsAppBatchesController extends Controller {
 private function actor(Request $r,bool $write=false):int{abort_unless(P::allows($r->user(),$write?P::MANAGE_CAMPAIGNS:P::VIEW)&&P::allows($r->user(),P::VIEW_AUDIENCE),403);return $r->user()->id;}
 public function index(Request $r,MarketingWhatsAppBatches $s,string $id){return response()->json(['batches'=>$s->listing($this->actor($r),$id)])->header('Cache-Control','private,no-store');}
 public function review(Request $r,MarketingWhatsAppBatches $s,string $id){$d=$r->validate(['version'=>'required|integer|min:1']);return response()->json(['batch'=>$s->review($this->actor($r,true),$id,$d['version'])],201)->header('Cache-Control','no-store');}
 public function confirm(Request $r,MarketingWhatsAppBatches $s,string $id,string $batch){$u=$this->actor($r,true);abort_unless(P::allows($r->user(),P::APPROVE_COST),403);$d=$r->validate(['version'=>'required|integer|min:1','confirmed'=>'required|accepted','cost_limit_minor'=>'required|integer|min:1|max:100000000']);return response()->json(['batch'=>$s->confirm($u,$id,$batch,$d)])->header('Cache-Control','no-store');}
 public function advance(Request $r,MarketingWhatsAppBatches $s,string $id,string $batch){return response()->json(['batch'=>$s->advance($this->actor($r,true),$id,$batch)])->header('Cache-Control','no-store');}
}
