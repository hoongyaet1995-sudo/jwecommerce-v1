<?php
namespace App\Http\Controllers\Admin;
use Illuminate\Http\Request;
use App\Services\Marketing\{MarketingFreePosts,MarketingPosts};
use App\Models\Product;
class MarketingFreePostController {
 public function page(Request $request,MarketingFreePosts $s){
  $s->authorize($request->user());$brand=app(\App\Services\Search\BusinessIdentity::class)->read();
  $form=['id'=>'instant-'.substr(hash('sha256',app(MarketingPosts::class)->merchant().':'.$request->user()->id),0,20),'version'=>0,'preset'=>'blank','name'=>'','source_scope'=>'all','market'=>'Malaysia','occasions'=>true,'live_trends'=>false,'sources'=>array_fill_keys(\App\Services\Marketing\MarketingAutomationSources::KINDS,[]),'prompt'=>'','type'=>'poster','image_mode'=>'single','cta_url'=>'','platforms'=>['Facebook','Instagram','TikTok'],'language'=>'English','tone'=>'Professional and approachable','theme'=>'#123e5a'];
  $form['source_modes']=app(\App\Services\Marketing\MarketingAutomationSources::class)->modes($form,false);
  $recent=\App\Models\MarketingInstantPostRun::where('user_id',$request->user()->id)->where('merchant_key',app(MarketingPosts::class)->merchant())->latest()->limit(10)->get();
  return response()->view('marketing.automation.index',['settings'=>true,'screen'=>'editor','instant'=>true,'brand'=>$brand,'form'=>$form,'labels'=>array_fill_keys(\App\Services\Marketing\MarketingAutomationSources::KINDS,[]),'recent'=>$recent,'recentViews'=>$recent->mapWithKeys(fn($run)=>[$run->id=>app(\App\Services\Marketing\MarketingInstantPosts::class)->view($run)])->all(),'studioNavActive'=>'posts'])->header('Cache-Control','private, no-store');
 }
 public function products(Request $r,MarketingFreePosts $s){
  $s->authorize($r->user());$v=$r->validate(['q'=>'nullable|string|max:100','ids'=>'nullable|array','ids.*'=>'integer|distinct']);$q=trim($v['q']??'');$ids=$v['ids']??[];
  if($q===''&&!$ids)return response()->json(['products'=>[],'more'=>false])->header('Cache-Control','private, no-store');
  $query=Product::where('status','active')->with('images')->orderBy('title')->orderBy('id');
  if($ids)$query->whereIn('id',$ids);else $query->whereRaw("title LIKE ? ESCAPE '!'",['%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%'])->limit(21);
  $rows=$query->get();$more=!$ids&&$rows->count()>20;if(!$ids)$rows=$rows->take(20);$products=[];$posts=app(MarketingPosts::class);
  foreach($rows as $p){foreach($p->images->sortBy('position') as $image){$path=$posts->imagePath($image->path);if(!$path||!@getimagesize($path))continue;$src=str_starts_with($image->path,'/')?$image->path:StorageUrl($image->path);$products[]=['id'=>$p->id,'title'=>$p->title,'description'=>mb_substr(strip_tags($p->description??''),0,600),'image'=>$src];break;}}
  return response()->json(['products'=>$products,'more'=>$more])->header('Cache-Control','private, no-store');
 }
 public function create(Request $r,MarketingFreePosts $s){return response()->json($s->view($s->prepare($r->user(),$r->all())));}
 public function status(Request $r,string $run,MarketingFreePosts $s){return response()->json($s->view($s->owned($r->user(),$run)))->header('Cache-Control','private, no-store');}
 public function step(Request $r,string $run,MarketingFreePosts $s){@set_time_limit(300);return response()->json($s->view($s->step($r->user(),$run)));}
}
function StorageUrl(string $path):string {return '/storage/'.ltrim(preg_replace('#^(?:storage/|public/)#','',$path),'/');}
