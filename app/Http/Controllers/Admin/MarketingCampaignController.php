<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Domain\Marketing\MarketingPermission as P;
use App\Services\Marketing\{MarketingCampaigns,MarketingPosts};
use Illuminate\Http\Request;
class MarketingCampaignController extends Controller {
 private function actor(Request $r,bool $edit=false):int{abort_unless(P::allows($r->user(),$edit?P::MANAGE_CAMPAIGNS:P::VIEW),403);return $r->user()->id;}
 public function boot(Request $r,MarketingCampaigns $s):array{$u=$this->actor($r);return ['whatsappDrafts'=>app(\App\Services\Marketing\MarketingWhatsAppPreparation::class)->listing($u),'campaigns'=>$s->listing($u),'posts'=>app(MarketingPosts::class)->listing($u),'accounts'=>app(MarketingPosts::class)->accounts(),'canEdit'=>P::allows($r->user(),P::MANAGE_CAMPAIGNS),'canEditPosts'=>P::allows($r->user(),P::EDIT),'canAnalytics'=>P::allows($r->user(),P::VIEW_ANALYTICS),'csrf'=>csrf_token(),'base'=>url('/admin/marketing'),'urls'=>MarketingNavigationController::boot('campaigns')['urls']];}
 public function index(Request $r,MarketingCampaigns $s){return response()->view('marketing.campaigns.index',['boot'=>$this->boot($r,$s)])->header('Cache-Control','private, no-store');}
 public function data(Request $r,MarketingCampaigns $s){return response()->json($this->boot($r,$s))->header('Cache-Control','private, no-store');}
 public function write(Request $r,MarketingCampaigns $s,?string $campaign=null){$u=$this->actor($r,true);$d=$r->validate(['name'=>'required|string|max:120','objective'=>'required|in:Product introduction,Brand awareness,Get a quote,Bookings,Purchases','start'=>'required|date_format:Y-m-d','end'=>'required|date_format:Y-m-d|after_or_equal:start','timezone'=>'required|timezone','destination_url'=>'required|url:http,https|max:2000','cta'=>'required|in:Learn more,Shop now,Get a quote,Book now','status'=>'sometimes|in:Draft,Active,Completed,Archived','version'=>'required|integer|min:0']);return response()->json(['campaign'=>$s->save($u,$d,$campaign)],$campaign?200:201);}
 public function assign(Request $r,MarketingCampaigns $s,string $campaign){$u=$this->actor($r,true);$d=$r->validate(['version'=>'required|integer|min:1','post_ids'=>'present|array|max:100','post_ids.*'=>'uuid|distinct']);return response()->json(['campaign'=>$s->assign($u,$campaign,$d['version'],$d['post_ids'])]);}
 public function link(Request $r){$u=$this->actor($r,true);abort_unless(P::allows($r->user(),P::EDIT),403,'Post editing access is required.');$d=$r->validate(['post_id'=>'required|uuid','destination_id'=>'required|uuid','version'=>'required|integer|min:0']);return response()->json(['link'=>app(\App\Services\Marketing\MarketingAttribution::class)->createLink($u,$d['post_id'],$d['destination_id'],$d['version'])]);}
}
