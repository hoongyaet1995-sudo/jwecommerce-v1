<?php
namespace App\Http\Controllers\Admin;
use App\Domain\Marketing\MarketingPermission;
use App\Services\Marketing\{MarketingDailyDrafts,MarketingPosts};
use Illuminate\Http\Request;
class MarketingDailyDraftController {
 public function __invoke(Request $request,string $run,MarketingDailyDrafts $service,MarketingPosts $posts) {
  abort_unless(MarketingPermission::allows($request->user(),MarketingPermission::VIEW),403);
  $record=$service->owned($request->user(),$run);$variants=[];
  foreach($record->post_ids??[] as $platform=>$id){$post=\App\Models\MarketingPost::where('user_id',$request->user()->id)->findOrFail($id);$doc=$posts->view($post);$variants[$platform]=['post'=>$doc,'asset'=>$posts->asset($request->user()->id,$doc['asset']),'editor'=>url('/admin/marketing/posts').'#editor/'.$id];}
  foreach($variants as &$variant){$variant['images']=array_values(array_filter(array_map(fn($key)=>$posts->asset($request->user()->id,$key),$variant['post']['assets']??[$variant['post']['asset']])));}unset($variant);
  return response()->view('marketing.posts.daily-draft',['record'=>$record,'variants'=>$variants])->header('Cache-Control','private, no-store');
 }
}
