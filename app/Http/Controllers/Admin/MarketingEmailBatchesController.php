<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Marketing\MarketingPermission as P;
use App\Services\Marketing\MarketingEmailBatches;
class MarketingEmailBatchesController extends Controller {
 private function actor(Request $r,bool $edit=false):int {abort_unless(P::allows($r->user(),$edit?P::MANAGE_CAMPAIGNS:P::VIEW)&&P::allows($r->user(),P::VIEW_AUDIENCE),403);return $r->user()->id;}
 public function index(Request $r,MarketingEmailBatches $s,string $id){return response()->json(['batches'=>$s->listing($this->actor($r),$id),'enabled'=>(bool)config('marketing.email_bulk_enabled'),'store_url'=>url('/')])->header('Cache-Control','private,no-store');}
 public function review(Request $r,MarketingEmailBatches $s,string $id){$u=$this->actor($r,true);$d=$r->validate(['version'=>'required|integer|min:1','landing_url'=>'required|url|max:2000']);return response()->json(['batch'=>$s->review($u,$id,$d)],201)->header('Cache-Control','no-store');}
 public function confirm(Request $r,MarketingEmailBatches $s,string $id,string $batch){$u=$this->actor($r,true);abort_unless(P::allows($r->user(),P::APPROVE_COST),403);$d=$r->validate(['version'=>'required|integer|min:1','confirmed'=>'required|accepted','cost_limit_minor'=>'required|integer|min:0|max:100000000']);return response()->json(['batch'=>$s->confirm($u,$id,$batch,$d)])->header('Cache-Control','no-store');}
 public function advance(Request $r,MarketingEmailBatches $s,string $id,string $batch){return response()->json(['batch'=>$s->advance($this->actor($r,true),$id,$batch)])->header('Cache-Control','no-store');}
}
