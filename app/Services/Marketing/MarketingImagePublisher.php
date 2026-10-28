<?php
namespace App\Services\Marketing;
use App\Models\{MarketingPost,MarketingChannelConnection,ProductImage};
use Illuminate\Support\Facades\{DB,Http,Storage,URL};
use Carbon\CarbonImmutable;
/** Owner-approved immediate image publishing only. No scheduler and no automatic resubmission. */
class MarketingImagePublisher {
 private function gate():void{abort_unless(config('marketing.social_publishing_enabled',false),423,'Live publishing is disabled. Your draft is retained.');}
 public function start(int $user,array $d):array {
  $this->gate();$s=app(MarketingPosts::class);$a=$s->asset($user,$d['asset']??'');abort_unless(($a['kind']??'')==='image'&&$d['type']==='Post',422,'This release publishes image Posts only. Videos remain drafts.');
  $image=ProductImage::findOrFail(substr($a['id'],6));$path=$s->imagePath($image->path);abort_unless($path&&filesize($path)<=20000000&&function_exists('imagecreatefromstring'),422,'The image cannot be prepared on this host. Original retained.');
  $bytes=file_get_contents($path);$src=@imagecreatefromstring($bytes);abort_unless($src,422,'The image is unreadable. Choose another original.');
  $w=imagesx($src);$h=imagesy($src);$canvas=imagecreatetruecolor(1080,1080);imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));$scale=min(1080/$w,1080/$h);$dw=(int)round($w*$scale);$dh=(int)round($h*$scale);imagecopyresampled($canvas,$src,(int)((1080-$dw)/2),(int)((1080-$dh)/2),0,0,$dw,$dh,$w,$h);ob_start();imagejpeg($canvas,null,94);$jpeg=ob_get_clean();imagedestroy($canvas);imagedestroy($src);
  $d['due']=now()->addMinute()->toIso8601String();$saved=$s->write($user,'schedule',$d);$file='marketing/posts/'.$saved['id'].'/r'.$saved['version'].'.jpg';Storage::disk('local')->put($file,$jpeg);$hash=hash('sha256',$jpeg);
  $ids=DB::transaction(function()use($user,$saved,$file,$hash){$p=MarketingPost::lockForUpdate()->findOrFail($saved['id']);abort_unless($p->version===$saved['version']&&$p->user_id===$user,409);$doc=$p->document;$ids=[];
   foreach($doc['targets'] as $target){if(($doc['outcomes'][$target]['status']??'')==='Published')continue;
    $c=MarketingChannelConnection::findOrFail($target);$id=DB::table('marketing_post_attempts')->insertGetId(['post_id'=>$p->id,'revision'=>$p->version,'connection_id'=>$target,'state'=>'claimed','receipt'=>json_encode(['stage'=>'claimed','connection_version'=>$c->version,'caption'=>$doc['copies'][$target]??$doc['copy'],'media_path'=>$file,'sha256'=>$hash]),'created_at'=>now(),'updated_at'=>now()]);$ids[]=$id;$doc['outcomes'][$target]=['status'=>'Publishing','ref'=>'Delivery '.$id];
   }$doc['history'][]=['title'=>'Immediate publishing approved','text'=>now()->toIso8601String().' · One request per destination. No automatic resend.'];$p->update(['document'=>$doc,'status'=>'Publishing','version'=>$p->version+1]);return $ids;
  });
  foreach($ids as $id)$this->advance($id,true);
  return $s->view(MarketingPost::findOrFail($saved['id']));
 }
 public function refresh(int $user,string $id):array {
  $this->gate();$p=MarketingPost::where('user_id',$user)->where('merchant_key',app(MarketingPosts::class)->merchant())->findOrFail($id);
  $stale=DB::table('marketing_post_attempts')->where('post_id',$id)->whereIn('state',['claimed','processing'])->where('updated_at','<',now()->subMinutes(2))->get();foreach($stale as $a){DB::transaction(function()use($a){$row=DB::table('marketing_post_attempts')->where('id',$a->id)->whereIn('state',['claimed','processing'])->lockForUpdate()->first();if(!$row)return;$receipt=json_decode($row->receipt,true);$receipt['reason']='unknown';$receipt['stage']='unknown';$this->saveReceipt($a->id,$receipt,'unknown');$post=MarketingPost::lockForUpdate()->findOrFail($a->post_id);$doc=$post->document;$doc['outcomes'][$a->connection_id]=['status'=>'Needs attention','reason'=>'unknown','ref'=>'Delivery '.$a->id];$post->update(['document'=>$doc,'status'=>'Needs attention','version'=>$post->version+1]);});}
  foreach(DB::table('marketing_post_attempts')->where('post_id',$id)->where('state','waiting')->pluck('id') as $attempt)$this->advance($attempt,false);
  return app(MarketingPosts::class)->view($p->fresh());
 }
 private function http($c){return Http::asForm()->acceptJson()->withToken($c->access_token)->connectTimeout(8)->timeout(25)->withoutRedirecting();}
 private function endpoint(string $path):string{$v=(string)config('services.facebook.graph_version','v25.0');abort_unless(preg_match('/^v\d+\.\d+$/D',$v),500);return 'https://graph.facebook.com/'.$v.'/'.$path;}
 private function request($c,string $method,string $path,array $data):array {
  $data['appsecret_proof']=hash_hmac('sha256',$c->access_token,(string)config('services.facebook.client_secret'));
  try{$r=$this->http($c)->{$method}($this->endpoint($path),$data);$body=$r->json();if(!$r->successful()||!is_array($body)||isset($body['error'])){$code=(int)($body['error']['code']??0);return ['failure'=>true,'reason'=>in_array($code,[10,190,200],true)?'permission':($r->clientError()?'media':'unknown'),'code'=>$code,'subcode'=>(int)($body['error']['error_subcode']??0)];}return $body;}catch(\Throwable){return ['failure'=>true,'reason'=>'unknown','code'=>0,'subcode'=>0];}
 }
 private function saveReceipt(int $id,array $r,string $state):void{DB::table('marketing_post_attempts')->where('id',$id)->update(['receipt'=>json_encode($r),'state'=>$state,'updated_at'=>now()]);}
 private function advance(int $id,bool $initial):void {
  // Atomically claim each network stage BEFORE transmission; refresh may only use a known container.
  $a=DB::table('marketing_post_attempts')->find($id);if(!$a||!in_array($a->state,$initial?['claimed']:['waiting'],true))return;
  if(!DB::table('marketing_post_attempts')->where('id',$id)->where('state',$a->state)->update(['state'=>'processing','updated_at'=>now()]))return;
  $receipt=json_decode($a->receipt,true);$c=MarketingChannelConnection::find($a->connection_id);$reason=null;$ref=null;$state='unknown';
  try {
   $cap=collect(app(MarketingPosts::class)->accounts())->firstWhere('id',$a->connection_id);if(!$c||!($cap['canSchedule']??false)||$c->version!==$receipt['connection_version']){$reason='permission';$state='failed';}
   elseif($c->platform==='facebook'&&$initial){$receipt['stage']='publish_started';$this->saveReceipt($id,$receipt,'processing');$res=$this->request($c,'post',$c->provider_account_id.'/photos',['url'=>URL::temporarySignedRoute('marketing.posts.delivery',now()->addMinutes(30),['attempt'=>$id]),'caption'=>$receipt['caption'],'published'=>'true']);if(isset($res['id'])){$ref=$res['post_id']??$res['id'];$state='published';}else{$reason=$res['reason']??'unknown';$state=$reason==='unknown'?'unknown':'failed';}$receipt['provider_code']=$res['code']??null;$receipt['provider_subcode']=$res['subcode']??null;}
   elseif($c->platform==='instagram'){
    if($initial){$receipt['stage']='container_started';$this->saveReceipt($id,$receipt,'processing');$res=$this->request($c,'post',$c->provider_account_id.'/media',['image_url'=>URL::temporarySignedRoute('marketing.posts.delivery',now()->addMinutes(30),['attempt'=>$id]),'caption'=>$receipt['caption']]);if(isset($res['id'])){$receipt['container_id']=$res['id'];$receipt['stage']='container_known';$this->saveReceipt($id,$receipt,'processing');}else{$reason=$res['reason']??'unknown';$receipt['provider_code']=$res['code']??null;$receipt['provider_subcode']=$res['subcode']??null;}}
    if(!$reason&&!empty($receipt['container_id'])){
     $res=$this->request($c,'get',$receipt['container_id'],['fields'=>'status_code']);$code=$res['status_code']??null;
     if($code==='FINISHED'){$receipt['stage']='publish_started';$this->saveReceipt($id,$receipt,'processing');$res=$this->request($c,'post',$c->provider_account_id.'/media_publish',['creation_id'=>$receipt['container_id']]);if(isset($res['id'])){$ref=$res['id'];$state='published';}else{$reason=$res['reason']??'unknown';$receipt['provider_code']=$res['code']??null;$receipt['provider_subcode']=$res['subcode']??null;}}
     elseif($code==='IN_PROGRESS'&&CarbonImmutable::parse($a->created_at)->gt(now()->subMinutes(10))){$state='waiting';$receipt['stage']='container_known';}
     else{$reason=in_array($code,['ERROR','EXPIRED'],true)?'media':($res['reason']??'unknown');}
    }if($reason&&$reason!=='unknown')$state='failed';
   }else{$reason='permission';$state='failed';}
  }catch(\Throwable){$reason='unknown';$state='unknown';}
  if(!in_array($state,['published','waiting','failed'],true)){$state='unknown';$reason='unknown';}
  $receipt['post_id']=$ref;$receipt['reason']=$reason;$receipt['stage']=$state==='published'?'completed':($state==='waiting'?'container_known':$state);$this->saveReceipt($id,$receipt,$state);
  DB::transaction(function()use($a,$state,$reason,$ref){$p=MarketingPost::lockForUpdate()->findOrFail($a->post_id);$doc=$p->document;$doc['outcomes'][$a->connection_id]=array_filter(['status'=>$state==='published'?'Published':($state==='waiting'?'Publishing':'Needs attention'),'reason'=>$reason,'ref'=>$ref?:'Delivery '.$a->id],fn($v)=>$v!==null);
   $values=array_column($doc['outcomes'],'status');$status=in_array('Publishing',$values,true)?'Publishing':(in_array('Needs attention',$values,true)?'Needs attention':'Published');$doc['history'][]=['title'=>'Destination '.strtolower($doc['outcomes'][$a->connection_id]['status']),'text'=>now()->toIso8601String().' · No automatic resend.'];$p->update(['document'=>$doc,'status'=>$status,'version'=>$p->version+1]);});
 }
 public function delivery(int $id){$a=DB::table('marketing_post_attempts')->find($id);abort_unless($a,404);$r=json_decode($a->receipt,true);abort_unless(isset($r['media_path'],$r['sha256']),404);$file=app(MarketingVideoFiles::class)->file($r['media_path'],$r['sha256']);return response()->file($file,['Content-Type'=>'image/jpeg','Cache-Control'=>'private, max-age=300','X-Content-Type-Options'=>'nosniff']);}
}
