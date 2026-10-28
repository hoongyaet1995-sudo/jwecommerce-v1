<?php
namespace App\Services\Marketing;
use App\Models\{MarketingPost,MarketingChannelConnection,ProductImage,MarketingTemplateRun,MarketingProject};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;

/** Preparation only. No production publisher or HTTP transport is registered. */
class MarketingPosts {
 public function merchant():string{return app(MarketingChannels::class)->merchant();}
 public function accounts():array {
  return MarketingChannelConnection::where('merchant_key',$this->merchant())->orderBy('name')->get()->map(function($c){
   $s=app(MarketingChannels::class)->summary($c);
   $can=$c->state==='connected'&&($s['capabilities']['identity_granted']??false)&&($s['capabilities']['publishing_granted']??false)&&(!$c->expires_at||$c->expires_at->isFuture()||(ExternalChannelConnections::supports($c->platform)&&$c->refresh_token&&(!$c->refresh_expires_at||$c->refresh_expires_at->isFuture())));
   if(in_array($c->platform,['youtube','tiktok'],true)&&!config('marketing.external_video_publishing_enabled',false))$can=false;
   if($c->platform==='facebook')$can=$can&&in_array('CREATE_CONTENT',$c->tasks??[],true);
   return ['id'=>$c->id,'platform'=>$c->platform,'name'=>$c->name,'kind'=>ucfirst($c->platform).' account','state'=>$s['state'],'canSchedule'=>$can,'publishingEnabled'=>false];
  })->all();
 }
 public function listing(int $user):array{return MarketingPost::where('user_id',$user)->where('merchant_key',$this->merchant())->orderByDesc('updated_at')->get()->map(fn($p)=>$this->view($p))->all();}
 public function view(MarketingPost $p):array{$doc=$p->document;$connections=MarketingChannelConnection::where('merchant_key',$p->merchant_key)->whereIn('id',array_keys($doc['outcomes']??[]))->get()->keyBy('id');foreach($doc['outcomes']??[] as $id=>$result){$doc['outcomes'][$id]['url']=app(MarketingPostLink::class)->url($connections[$id]->platform??'', $result,($doc['type']??'')==='Post'?'image':'video');}return array_merge($doc,($p->status==='Scheduled'&&empty($p->document['delivery_phase'])?['delivery_phase'=>'saved']:[]),['id'=>$p->id,'version'=>$p->version,'status'=>$p->status,'due'=>$p->scheduled_at?->toIso8601String(),'tz'=>$p->timezone]);}
 private function invalid(string $message):never{throw ValidationException::withMessages(['post'=>$message]);}
 public function write(int $user,string $action,array $d,bool $executing=false):array {
  return DB::transaction(function()use($user,$action,$d,$executing){
   $p=MarketingPost::whereKey($d['id'])->lockForUpdate()->first();
   if($p)abort_unless($p->user_id===$user&&$p->merchant_key===$this->merchant(),404);
   else {abort_unless(in_array($action,['draft','schedule']),404);abort_unless($d['version']===0,409,'The draft changed. Reload before saving.');$p=new MarketingPost(['id'=>$d['id'],'user_id'=>$user,'merchant_key'=>$this->merchant(),'version'=>0,'document'=>[]]);}
   abort_unless($p->version===$d['version'],409,'This post changed in another request. Your edits are retained; reload the saved version before continuing.');
   $old=$p->document;if(!$executing)abort_if(!empty($old['delivery_lock'])||$p->status==='Queued',409,'Publishing has started. You can still view its progress.');$outcomes=$old['outcomes']??[];
   foreach($outcomes as $o)abort_if(in_array($o['status']??'', ['Publishing'],true)||($o['reason']??'')==='unknown',409,'Submission is still being checked. Editing or resending is blocked to avoid duplicates.');
   if($action==='cancel'){
    abort_unless($p->status==='Scheduled',409,'Only a scheduled post can be cancelled.');
    foreach($old['targets'] as $t)if(($outcomes[$t]['status']??'')!=='Published')$outcomes[$t]=['status'=>'Cancelled'];
    $doc=$old;$doc['outcomes']=$outcomes;$status='Cancelled';$due=$p->scheduled_at;$tz=$p->timezone;
   }else {
    $targets=$d['targets']??[];$known=collect($this->accounts())->keyBy('id');
    foreach($targets as $t)if(!$known->has($t))$this->invalid('An account is unavailable in this business. Review the selected accounts.');
    foreach($outcomes as $t=>$o)if(($o['status']??'')==='Published'&&!in_array($t,$targets,true))$this->invalid('Already published destinations must be retained.');
    $copies=array_intersect_key($d['copies']??[],array_flip($targets));
    foreach($outcomes as $t=>$o)if(($o['status']??'')==='Published')$copies[$t]=$old['copies'][$t]??$old['copy'];
    $content=app(MarketingPostContent::class)->normalize($user,$d,$old);$d=array_replace($d,$content);
    $asset=$d['asset']??null;if($asset&&!$this->asset($user,$asset))$this->invalid('The selected media is unavailable. Choose an existing file again.');
    $doc=['title'=>trim($d['title']??'')?:mb_substr(trim($d['copy']??''),0,45),'copy'=>$d['copy']??'','targets'=>$targets,'copies'=>$copies,'asset'=>$asset,'type'=>$d['type'],'options'=>array_intersect_key($d['options']??[],array_flip($targets)),'history'=>$old['history']??[],'approvals'=>$old['approvals']??[]];
    $doc=array_replace($doc,$content);$doc['ai_context']=$d['ai_context']??$old['ai_context']??[];$doc['copy_history']=$d['copy_history']??$old['copy_history']??[];
    if($executing&&!empty($old['delivery_lock']))$doc['delivery_lock']=$old['delivery_lock'];$doc['title']=$doc['title']?:'Untitled post';$tz=$d['tz'];$due=isset($d['due'])?CarbonImmutable::parse($d['due'])->utc():null;
    if($action==='schedule'){
     if(!$due||$due->isPast())$this->invalid('Choose a time in the future.');$pending=0;
     foreach($targets as $t){if(($outcomes[$t]['status']??'')==='Published')continue;$pending++;$a=$known[$t];if(($asset||$a['platform']!=='facebook')&&!MarketingPostFormats::supports($a['platform'],$d['type'],$this->asset($user,$asset??'')['kind']??null))$this->invalid(ucfirst($a['platform']).': this content format is not supported in this release. Choose a supported image Post or video.');if($a['platform']==='youtube'&&(!$asset||($this->asset($user,$asset)['kind']??'')!=='video'))$this->invalid('YouTube requires a video. Image Posts are not supported for this destination.');if($a['platform']==='tiktok'&&(!$asset||!in_array($this->asset($user,$asset)['kind']??'',['image','video'],true)))$this->invalid('TikTok requires an image or video.');if(!$a['canSchedule'])$this->invalid($a['name'].': publishing access is required. Save a draft or review Channels.');if(!trim($copies[$t]??$doc['copy']))$this->invalid('Add post text for every destination.');if($a['platform']==='instagram'&&!$asset)$this->invalid('Instagram requires an image or video.');}
     app(MarketingPostContent::class)->validate($user,array_replace($doc,['outcomes'=>$outcomes]),$known->all());
     if(!$pending)$this->invalid('Choose at least one unpublished destination.');
     $m=$asset?$this->asset($user,$asset):null;if(($doc['type']==='Reel')!==($m&&$m['kind']==='video'))$this->invalid('Reels require a video. Choose Post for an image or text.');
     foreach($targets as $t)if(($outcomes[$t]['status']??'')!=='Published')$outcomes[$t]=['status'=>'Scheduled'];$status='Scheduled';
    }else {if($p->status==='Scheduled')$due=null;foreach($targets as $t)if(($outcomes[$t]['status']??'')!=='Published')unset($outcomes[$t]);$status='Draft';}
    $doc['outcomes']=array_intersect_key($outcomes,array_flip($targets));
   }
   if($action==='schedule')$doc['approvals'][]=['revision'=>$p->version+1,'due'=>$due->toIso8601String(),'timezone'=>$tz,'targets'=>array_values(array_filter($doc['targets'],fn($t)=>($outcomes[$t]['status']??'')!=='Published')),'copy'=>$doc['copy'],'copies'=>$doc['copies'],'asset'=>$doc['asset'],'type'=>$doc['type'],'options'=>$doc['options']??[],'assets'=>$doc['assets'],'media_copies'=>$doc['media_copies'],'format'=>$doc['format']];
   $doc['history'][]=['title'=>match($action){'draft'=>'Draft saved','schedule'=>'Schedule confirmed','cancel'=>'Schedule cancelled'},'text'=>now()->toIso8601String().' · Preparation saved. No platform request was made by this step.'];
   $p->fill(['status'=>$status,'scheduled_at'=>$due,'timezone'=>$tz,'document'=>$doc,'version'=>$p->version+1])->save();return $this->view($p);
  });
 }
 public function library(int $user,string $search='',int $page=1):array {
  $q=ProductImage::query()->join('products','products.id','=','product_images.product_id')->select('product_images.*','products.title as product_title');if($search!=='')$q->where('products.title','like','%'.$search.'%');
  $items=$q->orderBy('product_images.id')->offset(($page-1)*24)->limit(25)->get();$more=$items->count()>24;
  $rows=$items->take(24)->map(fn($i)=>$this->imageAsset($i))->filter()->values()->all();
  if($page===1){$runs=MarketingTemplateRun::whereIn('project_id',MarketingProject::where('owner_id',$user)->select('id'))->where('status','completed')->latest()->limit(15)->get();foreach($runs as $r)foreach(($r->result['formats']??[]) as $format=>$row){$a=$this->videoAsset($user,$r,$format);if($a&&($search===''||str_contains(strtolower($a['title']),strtolower($search))))$rows[]=$a;}}
  if($page===1){$uploaded=DB::table('marketing_post_media')->where('user_id',$user)->when($search!=='',fn($q)=>$q->where('title','like','%'.$search.'%'))->latest()->limit(24)->get();foreach($uploaded as $r){$a=$this->asset($user,'upload:'.$r->id);if($a)$rows[]=$a;}}
  return ['assets'=>$rows,'more'=>$more,'page'=>$page];
 }
 private function imageAsset($i):?array {
  $path=$this->imagePath($i->path);if(!$path)return null;
  return ['id'=>'image:'.$i->id,'title'=>$i->product_title??'Product image','kind'=>'image','src'=>route('marketing.posts.media',['asset'=>'image:'.$i->id]),'note'=>'Existing catalog image'];
 }
 public function imagePath(string $path):?string {
  if(preg_match('#^https?://#i',$path)){ $origin=parse_url(config('app.url'));$url=parse_url($path);if(($url['host']??'')!==($origin['host']??'')||($url['port']??null)!==($origin['port']??null)||isset($url['user'])||isset($url['pass'])||isset($url['query']))return null;$path=rawurldecode($url['path']??''); }
  $catalog=str_starts_with(ltrim($path,'/'),'images/');$path=$catalog?ltrim($path,'/'):preg_replace('#^(?:/storage/|storage/)#','',$path);
  if(str_contains($path,'..')||str_starts_with($path,'/')||str_contains($path,':'))return null;
  $root=realpath($catalog?public_path('images'):Storage::disk('public')->path(''));$file=realpath($catalog?public_path($path):Storage::disk('public')->path($path));
  return $root&&$file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)&&in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),['png','jpg','jpeg','webp','gif'],true)?$file:null;
 }
 private function videoAsset(int $user,$run,string $format):?array {
  if(!in_array($format,['portrait','landscape'],true)||!MarketingProject::whereKey($run->project_id)->where('owner_id',$user)->exists())return null;
  $row=$run->result['formats'][$format]??null;if(!$row)return null;
  try{app(MarketingVideoFiles::class)->file($row['path'],$row['sha256']);}catch(\Throwable){return null;}
  return ['id'=>'video:'.$run->id.':'.$format,'title'=>(MarketingProject::whereKey($run->project_id)->value('name')??'Completed video').' · '.$format.' · '.$run->created_at->format('d M H:i'),'kind'=>'video','src'=>route('marketing.posts.media',['asset'=>'video:'.$run->id.':'.$format]),'poster'=>null,'seconds'=>(float)($row['seconds']??15),'note'=>'Existing completed version'];
 }
 public function videoFile(int $user,string $key):string {
  if(str_starts_with($key,'upload:')){$r=DB::table('marketing_post_media')->where('user_id',$user)->where('id',substr($key,7))->first();abort_unless($r,404);return app(MarketingVideoFiles::class)->file($r->path,$r->sha256);}
  abort_unless($this->asset($user,$key),404);[$kind,$id,$format]=explode(':',$key);$r=MarketingTemplateRun::findOrFail($id)->result['formats'][$format];return app(MarketingVideoFiles::class)->file($r['path'],$r['sha256']);
 }
 public function asset(int $user,string $key):?array {
  if(preg_match('/^upload:([a-f0-9-]{36})$/D',$key,$m)){$r=DB::table('marketing_post_media')->where('user_id',$user)->where('id',$m[1])->first();if(!$r)return null;try{app(MarketingVideoFiles::class)->file($r->path,$r->sha256);}catch(\Throwable){return null;}return ['id'=>$key,'title'=>$r->title,'kind'=>str_ends_with($r->path,'.jpg')?'image':'video','src'=>route('marketing.posts.media',['asset'=>$key]),'seconds'=>(float)$r->seconds,'note'=>'Existing uploaded MP4 · Original retained'];}
  if(preg_match('/^image:(\d+)$/D',$key,$m)){$i=ProductImage::find($m[1]);return $i?$this->imageAsset($i):null;}
  if(preg_match('/^video:([a-f0-9-]{36}):(portrait|landscape)$/D',$key,$m)){$r=MarketingTemplateRun::where('status','completed')->find($m[1]);return $r?$this->videoAsset($user,$r,$m[2]):null;}
  return null;
 }
 /** Test-only adapter seam. Never exposed as a web action or registered as a worker. */
 public function executeFake(string $id,callable $adapter):void {
  abort_unless(app()->environment('testing'),403);
  DB::transaction(function()use($id,$adapter){$p=MarketingPost::lockForUpdate()->findOrFail($id);if($p->status!=='Scheduled')return;$doc=$p->document;
   foreach($doc['targets'] as $t){if(($doc['outcomes'][$t]['status']??'')!=='Scheduled')continue;
    $new=DB::table('marketing_post_attempts')->insertOrIgnore(['post_id'=>$id,'revision'=>$p->version,'connection_id'=>$t,'state'=>'claimed','created_at'=>now(),'updated_at'=>now()]);if(!$new)continue;
    try{$result=$adapter($t);}catch(\Throwable){$result='Unknown';}
    $reason=match($result){'Published'=>null,'Permission'=>'permission','Media'=>'media',default=>'unknown'};
    $doc['outcomes'][$t]=['status'=>$result==='Published'?'Published':'Needs attention','reason'=>$reason];DB::table('marketing_post_attempts')->where(['post_id'=>$id,'revision'=>$p->version,'connection_id'=>$t])->update(['state'=>$result,'updated_at'=>now()]);
   }
   $doc['history'][]=['title'=>'Fake adapter result','text'=>'Testing only. No external publishing.'];$p->update(['document'=>$doc,'status'=>collect($doc['outcomes'])->every(fn($o)=>$o['status']==='Published')?'Published':'Needs attention','version'=>$p->version+1]);
  });
 }
}
