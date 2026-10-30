<?php
namespace App\Services\Marketing\Audience;
use App\Services\Customers\ContactValueNormalizer as Normalize;
use App\Services\Marketing\MarketingPosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class AudienceDirectory {
 public function merchant():string{return app(MarketingPosts::class)->merchant();}
 public function query(){return DB::table('marketing_audience_contacts')->where('merchant_key',$this->merchant())->where('active',true);}
 public function hash(string $value):string{return hash('sha256',$value);}
 public function project(string $kind,string $source,array $d,string $run):?string {
  $email=Normalize::email($d['email']??null);$phone=Normalize::phone($d['phone']??null);
  if(!$email&&!$phone&&$kind!=='customer')return null;
  $existing=DB::table('marketing_audience_sources')->where(['merchant_key'=>$this->merchant(),'kind'=>$kind,'source_id'=>$source])->first();
  $matches=(!$email&&!$phone)?collect():$this->query()->where(function($q)use($email,$phone){if($email)$q->where('email',$email);if($phone)$q->orWhere('phone',$phone);})->orderBy('created_at')->get();
  $contact=$existing?DB::table('marketing_audience_contacts')->find($existing->contact_id):$matches->first();
  $conflict=$matches->count()>1||($existing&&$matches->contains(fn($m)=>$m->id!==$contact?->id));
  if($contact){if(($email&&$contact->email&&$email!==$contact->email)||($phone&&$contact->phone&&$phone!==$contact->phone))$conflict=true;
   if($kind==='customer'&&$contact->customer_id&&$contact->customer_id!==($d['customer_id']??null))$conflict=true;
  }
  if($conflict&&!$existing)$contact=null;
  if($conflict){foreach($matches as $m)DB::table('marketing_audience_contacts')->where('id',$m->id)->update(['conflict'=>true]);}
  $id=$contact?->id??(string)Str::uuid();$now=now();
  $values=['active'=>true,'updated_at'=>$now,'conflict'=>$conflict||($contact?->conflict??false)];
  if(!$contact)$values+=['id'=>$id,'merchant_key'=>$this->merchant(),'created_at'=>$now,'name'=>mb_substr($d['name']??'',0,255),'email'=>$email,'phone'=>$phone,'email_hash'=>$email?$this->hash($email):null,'phone_hash'=>$phone?$this->hash($phone):null,'tags'=>json_encode($d['tags']??[]),'customer_id'=>$d['customer_id']??null];
  elseif(!$conflict){if(!$contact->email&&$email){$values['email']=$email;$values['email_hash']=$this->hash($email);}if(!$contact->phone&&$phone){$values['phone']=$phone;$values['phone_hash']=$this->hash($phone);}if($kind==='customer')$values+=['customer_id'=>$d['customer_id'],'name'=>mb_substr($d['name']??'',0,255),'tags'=>json_encode($d['tags']??[])];}
  if($kind==='customer'&&!$conflict)$values['birthday_month']=$d['birthday_month']??null;
  if($contact)DB::table('marketing_audience_contacts')->where('id',$id)->update($values);else DB::table('marketing_audience_contacts')->insert($values);
  DB::table('marketing_audience_sources')->updateOrInsert(['merchant_key'=>$this->merchant(),'kind'=>$kind,'source_id'=>$source],['contact_id'=>$id,'run_id'=>$run,'tags'=>json_encode(array_slice(array_values(array_filter($d['tags']??[],fn($v)=>is_string($v)&&mb_strlen($v)<=80)),0,20)),'status'=>$d['status']??null,'currency'=>$d['currency']??null,'paid_minor'=>$d['paid_minor']??0,'refunded_minor'=>$d['refunded_minor']??0,'occurred_at'=>$d['occurred_at']??null,'active'=>true,'created_at'=>$existing?->created_at??$now,'updated_at'=>$now]);
  $this->recalculate($id);return $id;
 }
 public function recalculate(string $id):void {
  $q=DB::table('marketing_audience_sources')->where('contact_id',$id)->where('active',true);
  $tags=[];foreach((clone $q)->pluck('tags') as $raw)foreach(json_decode($raw??'[]',true)??[] as $tag)$tags[$tag]=true;
  $paid=(clone $q)->where('kind','order')->where('paid_minor','>',0);$enquiries=(clone $q)->where('kind','enquiry');
  DB::table('marketing_audience_contacts')->where('id',$id)->update(['active'=>(clone $q)->exists(),'tags'=>json_encode(array_keys($tags)),'paid_orders'=>(clone $paid)->count(),'last_paid_at'=>(clone $paid)->max('occurred_at'),'enquiries'=>(clone $enquiries)->count(),'last_enquiry_at'=>(clone $enquiries)->max('occurred_at')]);
  DB::table('marketing_audience_values')->where('contact_id',$id)->delete();
  foreach((clone $paid)->selectRaw('currency,SUM(paid_minor-refunded_minor) as net_minor')->groupBy('currency')->get() as $v)if($v->currency)DB::table('marketing_audience_values')->insert(['contact_id'=>$id,'currency'=>$v->currency,'net_minor'=>max(0,(int)$v->net_minor)]);
 }
 public function subscribe(string $channel,string $destination,string $state,string $source,$at):void {
  $key=['merchant_key'=>$this->merchant(),'channel'=>$channel,'destination_hash'=>$this->hash($destination)];$old=DB::table('marketing_audience_subscriptions')->where($key)->first();
  // Sync evidence never overwrites a later/local opt-out or suppression.
  if($old&&in_array($old->status,['unsubscribed','suppressed'],true)&&$state==='subscribed')return;
  DB::table('marketing_audience_subscriptions')->updateOrInsert($key,['status'=>$state,'source'=>$source,'effective_at'=>$at??now(),'created_at'=>$old?->created_at??now(),'updated_at'=>now()]);
 }
 public function eligibility(object $c,string $channel):array {
  $dest=$channel==='email'?$c->email:$c->phone;
  if($c->conflict)return ['eligible'=>false,'state'=>'identity_conflict'];
  if(!$c->active)return ['eligible'=>false,'state'=>'inactive'];
  if(!$dest)return ['eligible'=>false,'state'=>'missing_destination'];
  $hash=$this->hash($dest);
  $global=DB::table('marketing_audience_subscriptions')->where('merchant_key',$this->merchant())->where('channel','global')->whereIn('destination_hash',array_filter([$c->email?$this->hash($c->email):null,$c->phone?$this->hash($c->phone):null]))->where('status','suppressed')->exists();
  $s=DB::table('marketing_audience_subscriptions')->where(['merchant_key'=>$this->merchant(),'channel'=>$channel,'destination_hash'=>$hash])->first();
  $state=$global?'suppressed':($s->status??'unknown');return ['eligible'=>$state==='subscribed','state'=>$state];
 }
 public function decorateMany($contacts,bool $detail=false):array {
  $ids=$contacts->pluck('id')->all();if(!$ids)return [];
  $values=DB::table('marketing_audience_values')->whereIn('contact_id',$ids)->get()->groupBy('contact_id');
  $sq=DB::table('marketing_audience_sources')->whereIn('contact_id',$ids)->where('active',true);
  $sources=$detail?$sq->orderByDesc('occurred_at')->limit(100)->get()->groupBy('contact_id'):$sq->select('contact_id','kind')->distinct()->get()->groupBy('contact_id');
  $hashes=[];foreach($contacts as $c)foreach(['email','phone'] as $k)if($c->$k)$hashes[]=$this->hash($c->$k);
  $subs=DB::table('marketing_audience_subscriptions')->where('merchant_key',$this->merchant())->whereIn('destination_hash',$hashes)->get()->keyBy(fn($r)=>$r->channel.':'.$r->destination_hash);
  return $contacts->map(function($c)use($values,$sources,$subs){$out=(array)$c;$out['tags']=json_decode($c->tags??'[]',true)??[];$out['values']=($values[$c->id]??collect())->all();$out['sources']=($sources[$c->id]??collect())->all();$global=false;foreach(['email','phone'] as $k)if($c->$k&&(($subs['global:'.$this->hash($c->$k)]->status??null)==='suppressed'))$global=true;
   foreach(['email','sms','whatsapp'] as $ch){$dest=$ch==='email'?$c->email:$c->phone;$state=$c->conflict?'identity_conflict':(!$dest?'missing_destination':($global?'suppressed':($subs[$ch.':'.$this->hash($dest)]->status??'unknown')));$out[$ch.'_eligibility']=['state'=>$state,'eligible'=>$state==='subscribed'];}return $out;
  })->all();
 }
 public function decorate(object $c):array{return $this->decorateMany(collect([$c]),true)[0];}
 public function applyFilters($q,array $f){
  if(!empty($f['q'])){$v='%'.str_replace(['%','_'],['\%','\_'],mb_substr($f['q'],0,120)).'%';$q->where(fn($b)=>$b->where('name','like',$v)->orWhere('email','like',$v)->orWhere('phone','like',$v));}
  if(!empty($f['source']))$q->whereExists(fn($s)=>$s->selectRaw('1')->from('marketing_audience_sources')->whereColumn('contact_id','marketing_audience_contacts.id')->where('kind',$f['source'])->where('active',true));
  if(($f['activity']??'')==='paid')$q->where('paid_orders','>',0);if(($f['activity']??'')==='enquiry')$q->where('enquiries','>',0);
  if(!empty($f['subscription']))app(AudienceSegments::class)->condition($q,['field'=>'subscription','operator'=>'eq','channel'=>'email','value'=>$f['subscription']]);
  return $q;
 }
}
