<?php
namespace App\Services\Marketing;
use App\Models\{MarketingDailyDraftRun,Product,User};
use App\Services\AI\ProviderFailure;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
class MarketingDailyDrafts {
 public function prepare(User $user,string $id): MarketingDailyDraftRun {
  abort_unless(app()->environment('local','testing')&&Str::isUuid($id)&&$user->is_admin&&$user->is_super_admin,403);
  $old=MarketingDailyDraftRun::find($id);if($old){abort_unless($old->user_id===$user->id,404);return $old;}
  $connection=app(MarketingDailyDraftProvider::class)->connection();$eligible=[];$s=app(MarketingPosts::class);
  $recent=MarketingDailyDraftRun::where('user_id',$user->id)->where('status','completed')->where('created_at','>=',now()->subDays(30))->get()->pluck('snapshot.product_id')->all();
  foreach(Product::where('status','active')->with('images')->orderBy('id')->limit(500)->get() as $p){
   if(in_array($p->id,$recent,true)||!trim($p->title))continue;
   foreach($p->images->sortBy('position') as $i){$path=$s->imagePath($i->path);$info=$path?@getimagesize($path):false;
    if($info&&filesize($path)<=10485760&&$info[0]*$info[1]<=24000000&&in_array($info['mime'],['image/png','image/jpeg','image/webp'],true)){$eligible[]=['product'=>$p,'image'=>$i,'path'=>$path];break;}
   }
  }
  abort_unless($eligible,422,'No active unused product has a readable catalog image. No AI request sent.');
  $chosen=$eligible[random_int(0,count($eligible)-1)];$p=$chosen['product'];
  $identity=app(\App\Services\Search\BusinessIdentity::class)->read();
  abort_if($identity['name_source']==='fallback',422,'Confirm the business name in shop settings before generating. No AI request sent.');
  $snapshot=['product_id'=>$p->id,'title'=>$p->title,'description'=>mb_substr(trim(strip_tags((string)$p->description)),0,1600),'product_brand'=>$p->brand,'company'=>$identity['name'],'company_name_source'=>$identity['name_source'],'image_id'=>$chosen['image']->id,'image_path'=>$chosen['path'],'image_sha256'=>hash_file('sha256',$chosen['path']),'source_updated_at'=>$p->updated_at?->toIso8601String(),'selection_rule'=>'Uniform random among up to 500 ID-ordered active products with readable PNG/JPEG/WebP <=10MB/24MP; exclude completed choices in past30days.','eligible_count'=>count($eligible),'selected_at'=>now()->toIso8601String(),'price_policy'=>'Price and discounts deliberately omitted; no price inferred.','learning'=>'One inference using saved business facts; no trained or feedback-updated model.'];
  $snapshot['connection_version']=(int)$connection->version;
  $snapshot['company_logo']=$this->logo($snapshot['company']);
  return MarketingDailyDraftRun::create(['id'=>$id,'user_id'=>$user->id,'merchant_key'=>$s->merchant(),'status'=>'prepared','snapshot'=>$snapshot,'receipts'=>[],'post_ids'=>[]]);
 }
 public function owned(User $user,string $id): MarketingDailyDraftRun {return MarketingDailyDraftRun::where('user_id',$user->id)->where('merchant_key',app(MarketingPosts::class)->merchant())->findOrFail($id);}
 /** Original local shop branding only: no remote fetch, generated logo or alternate company fallback. */
 private function logo(string $company):?array {
  $identity=app(\App\Services\Search\BusinessIdentity::class)->read();abort_unless($identity['name']===$company,409,'Business identity changed.');
  if($identity['logo']==='')return null;
  $path=app(MarketingPosts::class)->imagePath($identity['logo']);$info=$path?@getimagesize($path):false;
  abort_unless($info&&filesize($path)<=10485760&&$info[0]*$info[1]<=24000000&&in_array($info['mime'],['image/png','image/jpeg','image/webp'],true),422,'The configured company logo cannot be read. Repair it in shop settings; no substitute logo will be generated.');
  return ['source'=>'shop_profile.data.shopLogo','reference'=>$identity['logo'],'path'=>$path,'sha256'=>hash_file('sha256',$path),'width'=>$info[0],'height'=>$info[1]];
 }
 private function verifySources(MarketingDailyDraftRun $r):void {
  abort_unless($r->merchant_key===app(MarketingPosts::class)->merchant(),404);
  $source=$r->snapshot['image_path'];abort_unless(is_file($source)&&hash_equals($r->snapshot['image_sha256'],hash_file('sha256',$source)),409,'Source image changed.');
  $p=Product::findOrFail($r->snapshot['product_id']);abort_unless($p->status==='active'&&$p->title===$r->snapshot['title']&&$p->updated_at?->toIso8601String()===$r->snapshot['source_updated_at'],409,'Source product changed.');
  $logo=$this->logo($r->snapshot['company']);abort_unless($logo===($r->snapshot['company_logo']??null),409,'Company logo changed; review and recompose locally.');
  $bg=Storage::disk('local')->path($r->snapshot['background_path']);abort_unless(is_file($bg)&&hash_equals($r->snapshot['background_sha256'],hash_file('sha256',$bg)),409,'Saved background changed.');
 }
 /** Explicit local-only repair; existing paid output and copy reused, old assets retained. */
 public function recompose(User $user,string $id):MarketingDailyDraftRun {
  abort_unless(app()->environment('local','testing')&&$user->is_admin&&$user->is_super_admin,403);$r=$this->owned($user,$id);abort_unless($r->status==='completed'&&count($r->post_ids)===3,409);
  $snap=$r->snapshot;$snap['company_logo']=$this->logo($snap['company']);$r->snapshot=$snap;$this->verifySources($r);
  return DB::transaction(function()use($r){$locked=MarketingDailyDraftRun::whereKey($r->id)->lockForUpdate()->firstOrFail();abort_unless($locked->status==='completed'&&$locked->post_ids===$r->post_ids,409);
   $posts=[];foreach($r->post_ids as $platform=>$id){$p=\App\Models\MarketingPost::where('user_id',$r->user_id)->where('merchant_key',$r->merchant_key)->lockForUpdate()->findOrFail($id);
    abort_unless($p->version===1&&$p->status==='Draft'&&empty($p->document['deleted_at'])&&empty($p->document['delivery_lock'])&&empty($p->document['outcomes'])&&!DB::table('marketing_post_attempts')->where('post_id',$id)->exists()&&($p->document['ai_context']['daily_draft_run']??null)===$r->id,409,'Only original unedited delivery-free drafts can be recomposed; review later edits explicitly.');$posts[$platform]=$p;
   }
   $previous=[];foreach(['facebook'=>[1080,1080],'instagram'=>[1080,1350],'tiktok'=>[1080,1920]] as $platform=>$size){$p=$posts[$platform];$doc=$p->document;$previous[$platform]=$doc['asset'];
    $file=$this->compose($r,$r->snapshot['image_path'],$platform,$size[0],$size[1]);$asset=app(MarketingPostMedia::class)->store($r->user_id,new UploadedFile($file,ucfirst($platform).' - '.$r->snapshot['title'].'.jpg','image/jpeg',null,true));
    // Preserve every existing caption, target and editing field; replace only the single generated image.
    abort_unless(($doc['format']??'image')==='image'&&count($doc['assets']??[])===1,409,'The draft media was edited; review it before recomposition.');
    $doc['asset']=$asset;$doc['assets']=[$asset];$p->update(['document'=>$doc,'version'=>$p->version+1]);
   }
   $snap=$r->snapshot;$snap['composition_history']=array_merge($snap['composition_history']??[],[['at'=>now()->toIso8601String(),'previous_assets'=>$previous,'reason'=>'Apply original shop logo without provider requests']]);$locked->update(['snapshot'=>$snap]);return $locked->fresh();
  });
 }
 public function generate(User $user,string $id): MarketingDailyDraftRun {
  abort_unless(app()->environment('local','testing')&&$user->is_super_admin,403);$r=$this->owned($user,$id);
  if($r->status==='completed')return $r;
  if(in_array($r->status,['image_completed','render_needs_attention'],true))return $this->finish($r);
  if($r->status!=='prepared')return $r; // uncertain outcomes are never resent
  abort_unless((int)app(MarketingDailyDraftProvider::class)->connection()->version===$r->snapshot['connection_version'],409,'AI Connect changed. No request sent.');
  $claim=MarketingDailyDraftRun::whereKey($id)->where('status','prepared')->update(['status'=>'text_requesting','updated_at'=>now()]);if(!$claim)return $r->fresh();
  try {
   $facts=array_intersect_key($r->snapshot,array_flip(['product_id','title','description','product_brand','company']));
   $text=app(MarketingDailyDraftProvider::class)->text($facts);$receipts=['text'=>array_diff_key($text,['raw'=>true])];$r->update(['receipts'=>$receipts]);
   abort_unless($text['completed']&&is_int($text['actual_microusd']),409,'Text result or cost is unconfirmed.');
   $plan=json_decode($text['raw'],true,32,JSON_THROW_ON_ERROR);$this->validate($plan,$r->snapshot);
   $r->update(['creative'=>$plan,'status'=>'text_completed']);
   abort_unless((int)app(MarketingDailyDraftProvider::class)->connection()->version===$r->snapshot['connection_version'],409,'AI Connect changed. Image not sent.');
   // One atomic claim immediately precedes image transport.
   $r->update(['status'=>'image_requesting']);
   $image=app(MarketingDailyDraftProvider::class)->image($plan['background_prompt']);$receipts['image']=$image['receipt'];$r->update(['receipts'=>$receipts]);
   abort_unless(is_string($image['encoded'])&&strlen($image['encoded'])<=15000000,409,'Image output unavailable.');
   $bytes=base64_decode($image['encoded'],true);$info=is_string($bytes)?@getimagesizefromstring($bytes):false;
   abort_unless($info&&$info['mime']==='image/png'&&$info[0]===1024&&$info[1]===1024&&strlen($bytes)<=10485760,409,'Invalid image result.');
   $path='marketing/daily-drafts/'.$id.'/background.png';Storage::disk('local')->put($path,$bytes);
   $snap=$r->snapshot;$snap['background_path']=$path;$snap['background_sha256']=hash('sha256',$bytes);$r->update(['snapshot'=>$snap,'status'=>'image_completed']);
  }catch(\Throwable $e){$r->refresh();$stage=str_starts_with($r->status,'image')?'image':'text';$r->update(['status'=>$stage.'_unknown','error_code'=>$e instanceof ProviderFailure?$e->reason:'result_or_cost_unconfirmed']);return $r->fresh();}
  return $this->finish($r->fresh());
 }
 private function validate($p,array $facts): void {
  abort_unless(is_array($p),422,'Creative JSON invalid.');
  foreach(['angle'=>180,'headline'=>60,'cta'=>100,'background_prompt'=>2200] as $key=>$max)abort_unless(is_string($p[$key]??null)&&trim($p[$key])!==''&&mb_strlen($p[$key])<=$max,422,'Creative field invalid.');
  abort_if(preg_match('/(?:https?:\/\/|\bRM\s*\d|\$\s*\d|\d+\s*%\s*off|guaranteed|certified|best[- ]selling)/i',$p['headline'].' '.$p['angle'].' '.$p['cta']),422,'Unsupported headline claim.');
  abort_unless(is_array($p['used_fact_ids']??null)&&!array_diff($p['used_fact_ids'],['title','description','product_brand','company'])&&in_array('title',$p['used_fact_ids'],true),422,'Unsupported source references.');
  abort_unless(is_array($p['variants']??null)&&count($p['variants'])===3,422,'Three platform captions are required.');
  foreach(['facebook','instagram','tiktok'] as $platform){$copy=$p['variants'][$platform]??null;abort_unless(is_string($copy)&&mb_strlen($copy)<=700&&str_contains($copy,$facts['title']),422,'Caption omitted its actual source product.');
   abort_if(preg_match('/(?:https?:\/\/|\bRM\s*\d|\$\s*\d|\d+\s*%\s*off|guaranteed|certified|best[- ]selling)/i',$copy),422,'Unsupported marketing claim.');
  }
 }
 private function finish(MarketingDailyDraftRun $r): MarketingDailyDraftRun {
  try {
   $this->verifySources($r);$source=$r->snapshot['image_path'];
   DB::transaction(function()use($r,$source){$locked=MarketingDailyDraftRun::whereKey($r->id)->lockForUpdate()->firstOrFail();if($locked->status==='completed')return;abort_unless(in_array($locked->status,['image_completed','render_needs_attention'],true),409);
    $ids=[];foreach(['facebook'=>[1080,1080],'instagram'=>[1080,1350],'tiktok'=>[1080,1920]] as $platform=>$size){
     $file=$this->compose($r,$source,$platform,$size[0],$size[1]);$asset=app(MarketingPostMedia::class)->store($r->user_id,new UploadedFile($file,ucfirst($platform).' - '.$r->snapshot['title'].'.jpg','image/jpeg',null,true));
     $post=app(MarketingPosts::class)->write($r->user_id,'draft',['id'=>(string)Str::uuid(),'version'=>0,'title'=>ucfirst($platform).' - '.$r->creative['angle'],'copy'=>$r->creative['variants'][$platform],'targets'=>[],'copies'=>[],'asset'=>$asset,'assets'=>[$asset],'format'=>'image','type'=>'Post','tz'=>'Asia/Kuala_Lumpur','ai_context'=>['source'=>'product','product_id'=>$r->snapshot['product_id'],'subject'=>$r->snapshot['title'],'facts'=>$r->snapshot['description'],'daily_draft_run'=>$r->id,'platform'=>$platform,'headline'=>$r->creative['headline'],'cta'=>$r->creative['cta']]]);$ids[$platform]=$post['id'];
    }$locked->update(['post_ids'=>$ids,'status'=>'completed','error_code'=>null]);
   });
  }catch(\Throwable){$r->refresh();if($r->status!=='completed')$r->update(['status'=>'render_needs_attention','error_code'=>'local_composition_or_save_failed']);}
  return $r->fresh();
 }
 private function compose(MarketingDailyDraftRun $r,string $source,string $platform,int $w,int $h): string {
  $bg=imagecreatefrompng(Storage::disk('local')->path($r->snapshot['background_path']));$photo=imagecreatefromstring(file_get_contents($source));$canvas=imagecreatetruecolor($w,$h);imagecopyresampled($canvas,$bg,0,0,0,0,$w,$h,imagesx($bg),imagesy($bg));
  $font=public_path('fonts/sample/barlow-semicondensed-Bold.ttf');$regular=public_path('fonts/sample/barlow-semicondensed-Regular.ttf');abort_unless(is_file($font)&&is_file($regular),422,'Local marketing fonts unavailable.');
  $ink=imagecolorallocate($canvas,5,40,27);$white=imagecolorallocate($canvas,255,255,255);$pad=70;
  imagefilledrectangle($canvas,40,35,$w-40,(int)($h*.36),$white);
  $logo=$r->snapshot['company_logo']??null;
  if($logo){$original=imagecreatefromstring(file_get_contents($logo['path']));abort_unless($original,422,'Company logo decode failed.');$scale=min(360/imagesx($original),80/imagesy($original));$lw=(int)round(imagesx($original)*$scale);$lh=(int)round(imagesy($original)*$scale);imagealphablending($canvas,true);imagecopyresampled($canvas,$original,$pad,55,0,0,$lw,$lh,imagesx($original),imagesy($original));imagedestroy($original);}
  else imagettftext($canvas,24,0,$pad,90,$ink,$font,mb_substr($r->snapshot['company'],0,55));
  $y=$logo?205:160;foreach(explode("\n",wordwrap($r->creative['headline'],27,"\n",true)) as $line){imagettftext($canvas,48,0,$pad,$y,$ink,$font,$line);$y+=62;}
  // The exact catalog photograph is contained intact, never synthesized or cropped.
  $boxX=70;$boxY=(int)($h*.39);$boxW=$w-140;$boxH=(int)($h*.43);imagefilledrectangle($canvas,$boxX-12,$boxY-12,$boxX+$boxW+12,$boxY+$boxH+12,$white);
  $scale=min($boxW/imagesx($photo),$boxH/imagesy($photo));$pw=(int)round(imagesx($photo)*$scale);$ph=(int)round(imagesy($photo)*$scale);imagecopyresampled($canvas,$photo,$boxX+(int)(($boxW-$pw)/2),$boxY+(int)(($boxH-$ph)/2),0,0,$pw,$ph,imagesx($photo),imagesy($photo));
  $y=(int)($h*.88);foreach(explode("\n",wordwrap($r->snapshot['title'],55,"\n",true)) as $line){imagettftext($canvas,23,0,$pad,$y,$ink,$regular,$line);$y+=32;}
  imagettftext($canvas,22,0,$pad,$h-45,$ink,$font,$r->creative['cta']);
  $path='marketing/daily-drafts/'.$r->id.'/'.$platform.'.jpg';$file=Storage::disk('local')->path($path);imagejpeg($canvas,$file,94);foreach([$canvas,$bg,$photo] as $im)imagedestroy($im);return $file;
 }
}
