<?php
namespace App\Http\Controllers\Admin;
use Illuminate\Http\Request;
use App\Domain\Marketing\MarketingPermission;
use App\Services\Marketing\{MarketingPostAutomations,MarketingAutomationSources,MarketingPosts};
use App\Models\{MarketingPostAutomation,MarketingAutomationRun};
class MarketingAutomationController {
 public function __invoke(Request $r,MarketingPostAutomations $service){
  abort_unless(MarketingPermission::allows($r->user()),403);$settings=$r->routeIs('marketing.automation.ai-posts');
  $rows=$settings?MarketingPostAutomation::where('user_id',$r->user()->id)->where('merchant_key',app(MarketingPosts::class)->merchant())->latest()->get():collect();
  return $this->page(['settings'=>$settings,'screen'=>'list','automations'=>$rows,'presets'=>$service->presets()]);
 }
 private function page(array $data){return response()->view('marketing.automation.index',$data+['studioNavActive'=>'automation'])->header('Cache-Control','private, no-store');}
 public function create(Request $r,MarketingPostAutomations $s){abort_unless(MarketingPermission::allows($r->user(),MarketingPermission::EDIT),403);return $this->page(['settings'=>true,'screen'=>'presets','presets'=>$s->presets()]);}
 public function editor(Request $r,MarketingPostAutomations $s,?string $automation=null){
  abort_unless(MarketingPermission::allows($r->user(),MarketingPermission::EDIT),403);$a=$automation?$s->owned($r->user(),$automation):null;$presets=$s->presets();$preset=$a?->preset??$r->query('preset','blank');abort_unless(isset($presets[$preset]),404);
  $v=$a?->settings??['source_scope'=>'all','market'=>'Malaysia','occasions'=>true,'live_trends'=>false,'sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>$presets[$preset]['prompt'],'type'=>$presets[$preset]['type'],'image_mode'=>$presets[$preset]['type']==='carousel'?'multiple':'single','cta_url'=>'','platforms'=>['Facebook','Instagram','TikTok'],'language'=>'English','tone'=>'Professional and approachable','theme'=>'#123e5a','days'=>[],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur'];
  $v['source_modes']=app(MarketingAutomationSources::class)->modes($v,false);$v+=['source_scope'=>'selected','market'=>'Malaysia','occasions'=>true,'live_trends'=>false];$recent=$a?MarketingAutomationRun::where('automation_id',$a->id)->where('user_id',$r->user()->id)->latest()->limit(10)->get():collect();
  return $this->page(['settings'=>true,'screen'=>'editor','presets'=>$presets,'brand'=>app(\App\Services\Search\BusinessIdentity::class)->read(),'form'=>$v+['id'=>$a?->id??(string)\Illuminate\Support\Str::uuid(),'version'=>$a?->version??0,'name'=>$a?->name??($preset==='blank'?'':$presets[$preset]['name']),'preset'=>$preset],'labels'=>app(MarketingAutomationSources::class)->labels($v['sources']),'recent'=>$recent,'recentViews'=>$recent->mapWithKeys(fn($run)=>[$run->id=>$s->view($run)])->all()]);
 }
 public function save(Request $r,MarketingPostAutomations $s){$a=$s->save($r->user(),$r->all());return response()->json(['id'=>$a->id,'version'=>$a->version,'edit_url'=>url('/admin/marketing/automation/ai-posts/'.$a->id.'/edit')]);}
 public function sources(Request $r,MarketingAutomationSources $s){abort_unless(MarketingPermission::allows($r->user(),MarketingPermission::EDIT),403);return response()->json($s->search($r->all()))->header('Cache-Control','private, no-store');}
 public function preview(Request $r,\App\Services\Marketing\MarketingSourcePoolPreview $s){abort_unless(MarketingPermission::allows($r->user(),MarketingPermission::EDIT),403);return response()->json($s->read($r->all()))->header('Cache-Control','private, no-store');}
 public function generate(Request $r,string $automation,MarketingPostAutomations $s){return response()->json($s->view($s->prepare($r->user(),$automation,$r->all())));}
 public function step(Request $r,string $run,MarketingPostAutomations $s){set_time_limit(300);return response()->json($s->step($r->user(),$run));}
 public function status(Request $r,string $run,MarketingPostAutomations $s){return response()->json($s->view($s->run($r->user(),$run)))->header('Cache-Control','private, no-store');}
}
