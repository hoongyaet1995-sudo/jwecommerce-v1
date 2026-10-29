<?php

namespace App\Services\Marketing;

use App\Models\{MarketingCampaign,MarketingPost};

use Illuminate\Support\Facades\DB;

class MarketingCampaigns {

 public function query(int $user){return MarketingCampaign::where('merchant_key',app(MarketingPosts::class)->merchant())->where('user_id',$user);}

 public function view(MarketingCampaign $c):array{return ['channel'=>$c->channel??'post','document'=>$c->channel_document??[],'updated_at'=>$c->updated_at?->toIso8601String(),'id'=>$c->id,'name'=>$c->name,'objective'=>$c->objective,'status'=>$c->status,'start'=>$c->starts_on,'end'=>$c->ends_on,'timezone'=>$c->timezone,'url'=>$c->destination_url,'cta'=>$c->cta,'version'=>$c->version,'posts'=>MarketingPost::where('campaign_id',$c->id)->count()];}

 public function listing(int $user):array{return $this->query($user)->latest('updated_at')->get()->map(fn($c)=>$this->view($c))->all();}

 public function save(int $user,array $d,?string $id=null):array{return DB::transaction(function()use($user,$d,$id){$c=$id?$this->query($user)->lockForUpdate()->findOrFail($id):new MarketingCampaign(['merchant_key'=>app(MarketingPosts::class)->merchant(),'user_id'=>$user,'version'=>0]);abort_if($c->exists&&($c->channel??'post')!=='post',409,'Open this campaign in its channel editor.');abort_unless($c->version===$d['version'],409,'This campaign changed. Reload before saving.');$url=parse_url($d['destination_url']);abort_if(isset($url['user'])||isset($url['pass'])||!in_array($url['scheme']??'',['https','http'],true),422,'Choose a valid website address.');$c->fill(['name'=>$d['name'],'objective'=>$d['objective'],'starts_on'=>$d['start'],'ends_on'=>$d['end'],'timezone'=>$d['timezone'],'destination_url'=>$d['destination_url'],'cta'=>$d['cta'],'status'=>$d['status']??$c->status??'Draft','version'=>$c->version+1]);$c->save();return $this->view($c);});}

 public function saveChannelDraft(int $u,array $d,?string $id):array {return DB::transaction(function()use($u,$d,$id){

  $c=$id?$this->query($u)->lockForUpdate()->findOrFail($id):new MarketingCampaign(['merchant_key'=>app(MarketingPosts::class)->merchant(),'user_id'=>$u,'version'=>0]);

  abort_unless($c->version===$d['version'],409,'This campaign changed. Reload before saving.');

  abort_if($c->exists&&($c->channel!==$d['channel']||$c->status!=='Draft'),409,'Only a draft of the same channel can be edited here.');

  $doc=$d['document'];$template=null;

  if(!empty($doc['template_id'])){$template=app(MarketingContentTemplates::class)->query($u)->where('channel',$d['channel'])->whereNull('archived_at')->find($doc['template_id']);abort_unless($template,422,'Choose an available template for this channel.');}

  // Preparing a draft never subscribes contacts, queues delivery, or infers consent.

  $doc['template_version']=$template?->version;

  $c->fill(['name'=>$d['name'],'channel'=>$d['channel'],'channel_document'=>$doc,'status'=>'Draft','version'=>$c->version+1]);

  if(!$c->exists)$c->fill(['objective'=>'Brand awareness','starts_on'=>today()->toDateString(),'ends_on'=>today()->toDateString(),'timezone'=>config('app.timezone'),'destination_url'=>url('/'),'cta'=>'Learn more']);

  $c->save();return $this->view($c);

 });}

 public function assign(int $user,string $id,int $version,array $ids):array{abort_unless(count($ids)<=1,422,'Choose one Post template per campaign.');return DB::transaction(function()use($user,$id,$version,$ids){$c=$this->query($user)->lockForUpdate()->findOrFail($id);abort_unless(($c->channel??'post')==='post',422,'Only Post campaigns can contain social posts.');abort_unless($c->version===$version,409,'This campaign changed. Reload before updating its posts.');abort_if($c->status==='Archived',409,'Restore the campaign before changing content.');$all=MarketingPost::where('merchant_key',$c->merchant_key)->where('user_id',$user)->where(function($q)use($id,$ids){$q->where('campaign_id',$id)->orWhereIn('id',$ids);})->orderBy('id')->lockForUpdate()->get();abort_unless(count(array_intersect($all->pluck('id')->all(),$ids))===count($ids),404);foreach($all as $p){$target=in_array($p->id,$ids,true)?$id:null;if($p->campaign_id===$target)continue;abort_if($p->campaign_id&&$p->campaign_id!==$id,409,'This post belongs to another campaign.');abort_unless($p->status==='Draft'&&empty($p->document['delivery_lock'])&&empty($p->document['outcomes']),409,'Only an unpublished draft can change campaign.');$p->forceFill(['campaign_id'=>$target,'version'=>$p->version+1])->save();}$c->increment('version');return $this->view($c->fresh());});}

}
