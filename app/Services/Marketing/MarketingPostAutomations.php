<?php
namespace App\Services\Marketing;

use App\Models\{MarketingPostAutomation as Automation,MarketingAutomationRun as Run,User};
use Illuminate\Support\Facades\{DB,Validator};

class MarketingPostAutomations
{
 public function presets():array {
  return [
   'blank'=>['name'=>'Create Blank','type'=>'poster','purpose'=>'Build your own direction','prompt'=>''],
   'spotlight'=>['name'=>'Product Spotlight','type'=>'poster','purpose'=>'Discovery & awareness','prompt'=>'Choose a relevant product or complementary products. Present one memorable everyday benefit using confirmed facts, and invite the audience to explore.'],
   'offers'=>['name'=>'Promotion & Offers','type'=>'promotion','purpose'=>'Conversion','prompt'=>'Choose a currently valid promotion and suitable products. Highlight the confirmed benefit and essential voucher conditions. Do not invent discounts, prices, urgency or stock.'],
   'education'=>['name'=>'Product Education','type'=>'infographic','purpose'=>'Build trust','prompt'=>'Choose useful product or published page facts. Explain an everyday problem and 3 practical points clearly. Avoid unsupported advice or performance claims.'],
   'guide'=>['name'=>'Buying Guide','type'=>'carousel','purpose'=>'Consideration & saves','prompt'=>'Choose related products or published buying guidance. Explain meaningful differences and who each option suits, using verified facts. One useful point per image.'],
   'checklist'=>['name'=>'Tips & Checklist','type'=>'checklist','purpose'=>'Saves & shares','prompt'=>'Turn confirmed product or published guidance into a concise checklist. Make the points useful, specific and easy to save.'],
   'moments'=>['name'=>'Relatable Moments','type'=>'meme','purpose'=>'Engagement','prompt'=>'Choose a relatable everyday situation from the source facts. Create original, light-hearted humour that invites comments, without inventing real customer evidence.'],
   'story'=>['name'=>'Brand Story','type'=>'storytelling','purpose'=>'Brand connection','prompt'=>'Tell a concise story drawn from the selected published company or product source. Use only documented events; do not invent founder history or testimonials.'],
   'case'=>['name'=>'Case Study','type'=>'education','purpose'=>'Trust & consideration','prompt'=>'Explain a documented problem and solution from the selected source. Use only supported details and results; if no real case is documented, provide an educational explanation without claiming a real customer case.'],
  ];
 }
 public function owned(User $u,string $id):Automation {return Automation::where('user_id',$u->id)->where('merchant_key',app(MarketingPosts::class)->merchant())->findOrFail($id);}
 public function save(User $u,array $input):Automation {
  app(MarketingFreePosts::class)->authorize($u);
  $rules=['id'=>'required|uuid','version'=>'required|integer|min:0','name'=>'required|string|max:120','preset'=>'required|in:'.implode(',',array_keys($this->presets())),'sources'=>'required|array:'.implode(',',MarketingAutomationSources::KINDS),'prompt'=>'required|string|max:1200','type'=>'required|in:'.implode(',',MarketingFreePosts::TYPES),'image_mode'=>'required|in:single,multiple','cta_url'=>'nullable|string|max:2048','platforms'=>'required|array|min:1|max:3','platforms.*'=>'distinct|in:Facebook,Instagram,TikTok','language'=>'required|in:English,中文,Bahasa Melayu','tone'=>'required|string|max:100','theme'=>'required|regex:/^#[0-9a-fA-F]{6}$/','days'=>'present|array|max:7','days.*'=>'integer|distinct|min:1|max:7','time'=>'required|date_format:H:i','timezone'=>'required|in:Asia/Kuala_Lumpur'];
  foreach(MarketingAutomationSources::KINDS as $k){$rules['sources.'.$k]='present|array';$rules['sources.'.$k.'.*']='integer|distinct';}
  $rules['schedule_enabled']='sometimes|boolean';$rules['days']=!empty($input['schedule_enabled'])?'required|array|min:1|max:7':'present|array|max:7';
  $rules+=['source_modes'=>'sometimes|array:'.implode(',',MarketingAutomationSources::KINDS),'source_scope'=>'sometimes|in:all,selected','market'=>'sometimes|in:'.implode(',',MarketingCreativeIntelligence::MARKETS),'occasions'=>'sometimes|boolean','live_trends'=>'sometimes|boolean'];$v=Validator::make($input,$rules)->validate();$v['days']=array_map('intval',$v['days']);$v+=['source_scope'=>'selected','market'=>'Malaysia','occasions'=>true,'live_trends'=>false];$v['cta_url']=empty($v['cta_url'])?null:app(MarketingPostCaption::class)->customDestination($v['cta_url']);app(MarketingAutomationSources::class)->validateSettings($v);$v['source_modes']=app(MarketingAutomationSources::class)->modes($v,false);
  return DB::transaction(function()use($u,$v){$a=Automation::whereKey($v['id'])->lockForUpdate()->first();if($a){$this->owned($u,$a->id);abort_unless($a->version===$v['version'],409,'This automation changed. Reload before saving.');}else abort_unless($v['version']===0,409,'Automation version is no longer current.');
   $enabled=(bool)($v['schedule_enabled']??$a?->schedule_enabled??false);if($enabled&&!$v['days'])throw \Illuminate\Validation\ValidationException::withMessages(['days'=>'Choose at least one day for an active schedule.']);$settings=$v;unset($settings['id'],$settings['version'],$settings['name'],$settings['preset'],$settings['schedule_enabled']);$same=$a&&$a->name===$v['name']&&$a->preset===$v['preset']&&$a->settings==$settings&&$a->schedule_enabled===$enabled;if($same)return $a;$reset=$enabled&&(!$a?->schedule_enabled||$a->settings!=$settings);$data=['user_id'=>$u->id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'name'=>$v['name'],'preset'=>$v['preset'],'version'=>$v['version']+1,'settings'=>$settings,'schedule_enabled'=>$enabled,'schedule_epoch'=>$reset?now('UTC'):($a?->schedule_epoch),'schedule_checked_at'=>$reset?now('UTC'):($a?->schedule_checked_at)];if($a){$a->update($data);return $a->fresh();}return Automation::create(['id'=>$v['id']]+$data);});
 }
 public function run(User $u,string $id):Run {app(MarketingFreePosts::class)->authorize($u);return Run::where('user_id',$u->id)->where('merchant_key',app(MarketingPosts::class)->merchant())->findOrFail($id);}
 public function prepare(User $u,string $automation,array $input):Run {
  app(MarketingFreePosts::class)->authorize($u);$v=Validator::make($input,['id'=>'required|uuid','version'=>'required|integer|min:1'])->validate();$a=$this->owned($u,$automation);
  if(Run::find($v['id'])){$r=$this->run($u,$v['id']);abort_unless($r->automation_id===$a->id&&$r->snapshot['version']===$v['version'],409);return $r;}
  abort_unless($a->version===$v['version'],409,'Save or reload the latest settings before generating.');$provider=app(MarketingFreePostProvider::class);$connection=$provider->connection();$intelligence=app(MarketingCreativeIntelligence::class)->context($a->merchant_key,$a->settings);$pool=app(MarketingAutomationSources::class)->pool($a->settings+['_recent_product_ids'=>$intelligence['recent_product_ids'],'_rotation'=>$intelligence['memory']['total']]);
  return Run::create(['id'=>$v['id'],'automation_id'=>$a->id,'user_id'=>$u->id,'merchant_key'=>$a->merchant_key,'status'=>'selection_prepared','snapshot'=>['settings'=>$a->settings,'version'=>$a->version,'pool'=>$pool,'intelligence'=>$intelligence,'connection_version'=>$connection->version]]);
 }
 public function step(User $u,string $id):array {
  $r=$this->run($u,$id);$s=$r->snapshot;
  if($r->status==='selection_prepared'){
   abort_unless(app(MarketingFreePostProvider::class)->connection()->version===$s['connection_version'],409,'AI Connect changed. Create a new generation.');
   if(!Run::whereKey($id)->where('status','selection_prepared')->update(['status'=>'selection_requesting']))return $this->view($r->fresh());
   try{$out=app(MarketingFreePostProvider::class)->selectSources(['direction'=>$s['settings']['prompt'],'type'=>$s['settings']['type'],'pool'=>$s['pool'],'creative_intelligence'=>$s['intelligence']??[]]);$r->update(['receipt'=>$out['receipt']]);abort_unless($out['completed'],409);$answer=json_decode($out['raw'],true,32,JSON_THROW_ON_ERROR);$chosen=app(MarketingAutomationSources::class)->selected($s['pool'],$answer);$chosen['reason']=$answer['reason'];foreach(['occasion_id'=>'occasions','trend_id'=>'trends'] as $key=>$kind){$items=$kind==='trends'?($s['intelligence']['trends']['items']??[]):($s['intelligence']['occasions']??[]);$value=$answer[$key]??null;abort_if($value!==null&&!in_array($value,array_column($items,'id'),true),422,'AI selected context outside verified inputs.');$chosen[$key]=$value;}$r->update(['selection'=>$chosen,'status'=>'selection_completed']);}
   catch(\Throwable $e){$r->update(['status'=>'selection_unknown','error_code'=>$e instanceof \App\Services\AI\ProviderFailure?$e->reason:'selection_unconfirmed']);}
   return $this->view($r->fresh());
  }
  if($r->status==='selection_completed'){
   // Preparation is local and repeatable. A durable child UUID prevents duplicate provider work.
   $chosen=$r->selection;$chosen['allowed_sources']=$s['settings']['sources'];$chosen['source_scope']=$s['settings']['source_scope']??'selected';$chosen['source_modes']=app(MarketingAutomationSources::class)->modes($s['settings'],false);app(MarketingAutomationSources::class)->verify($chosen);
   foreach($chosen['products'] as $old){$p=\App\Models\Product::findOrFail($old['id']);abort_unless($p->status==='active'&&$p->title===$old['title']&&mb_substr(strip_tags($p->description??''),0,700)===$old['facts'],409,'A candidate product changed. Create a new generation.');}
   $settings=$s['settings'];$notes=$settings['prompt'];$evidence=[];foreach($chosen['products'] as $p)$evidence[]=$p['title']."\n".$p['facts'];foreach($chosen['pages'] as $p)$evidence[]=$p['title']."\n".$p['facts'];foreach($chosen['promotions'] as $p){$evidence[]='Confirmed voucher terms (amount values in minor currency units): '.json_encode($p);if($p['discount_type']==='percentage')$notes.="\nConfirmed voucher ".$p['code'].': '.$p['value'].'% OFF; all supplied voucher conditions apply.';}
   abort_if(mb_strlen($notes)>1200||mb_strlen(implode("\n\n",$evidence))>3000,422,'Selected content exceeds the generation brief capacity. Narrow the source pool or shorten the direction.');
   $brief=['id'=>$id,'product_ids'=>array_column($chosen['products'],'id'),'type'=>$settings['type'],'reference'=>'ai-direction','slides'=>3,'theme'=>$settings['theme'],'goal'=>'Create a useful marketing post','language'=>$settings['language'],'tone'=>$settings['tone'],'audience'=>'','notes'=>$notes,'evidence'=>implode("\n\n",$evidence),'platforms'=>$settings['platforms'],'image_mode'=>$settings['image_mode'],'cta_url'=>$settings['cta_url']];
   $context=$s['intelligence']??[];if($context){$context['occasions']=array_values(array_filter($context['occasions']??[],fn($i)=>$i['id']===($chosen['occasion_id']??null)));$context['trends']['items']=array_values(array_filter($context['trends']['items']??[],fn($i)=>$i['id']===($chosen['trend_id']??null)));}$child=app(MarketingFreePosts::class)->prepare($u,$brief,['automation_id'=>$r->automation_id,'sources'=>$chosen,'intelligence'=>$context,'selection_reason'=>$chosen['reason']??'','subject'=>$chosen['pages'][0]['title']??$chosen['promotions'][0]['name']??$chosen['promotions'][0]['code']??'']);$receipts=$child->receipts??[];$receipts['source_selection']=$r->receipt;$child->update(['receipts'=>$receipts]);$r->update(['status'=>'generating']);return $this->view($r->fresh());
  }
  if($r->status==='generating'){app(MarketingFreePosts::class)->step($u,$id);}
  return $this->view($r->fresh());
 }
 public function view(Run $r):array {
  if($r->status==='generating')return app(MarketingFreePosts::class)->view(\App\Models\MarketingDailyDraftRun::findOrFail($r->id))+['automation_id'=>$r->automation_id];
  return ['id'=>$r->id,'status'=>$r->status,'automation_id'=>$r->automation_id,'can_step'=>in_array($r->status,['selection_prepared','selection_completed'],true),'error'=>$r->error_code,'review_url'=>null,'completed_images'=>0,'total_images'=>null];
 }
}
