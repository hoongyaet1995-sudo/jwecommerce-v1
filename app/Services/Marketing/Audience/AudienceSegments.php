<?php
namespace App\Services\Marketing\Audience;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class AudienceSegments {
 public function validate(array $r):array {
  $fail=fn()=>throw ValidationException::withMessages(['rules'=>'Choose supported fields, operators and values for every rule.']);
  if(!in_array($r['match']??'', ['all','any'],true)||!is_array($r['conditions']??null)||count($r['conditions'])<1||count($r['conditions'])>10)$fail();
  foreach($r['conditions'] as $c){if(!is_array($c))$fail();$f=$c['field']??'';$o=$c['operator']??'';$v=$c['value']??null;
   if(in_array($f,['paid_orders','enquiries','net_minor'],true)){if(!in_array($o,['eq','gte','lte'],true)||!is_numeric($v)||!ctype_digit((string)$v)||(float)$v>1000000000000)$fail();if($f==='net_minor'&&!preg_match('/^[A-Z]{3}$/D',$c['currency']??''))$fail();}
   elseif($f==='birthday_month'){if($o!=='eq'||!ctype_digit((string)$v)||(int)$v<1||(int)$v>12)$fail();}
   elseif($f==='category_purchased'){if($o!=='eq'||!ctype_digit((string)$v)||!DB::table('categories')->where('id',$v)->exists())$fail();}
   elseif(in_array($f,['first_paid_at','last_paid_at','last_enquiry_at'],true)&&in_array($o,['within_last_days','more_than_days_ago','between_days_ago'],true)){if(!ctype_digit((string)$v)||(int)$v>36500)$fail();if($o==='between_days_ago'&&(!ctype_digit((string)($c['value_to']??''))||(int)$c['value_to']<=(int)$v||(int)$c['value_to']>36500))$fail();}
   elseif(in_array($f,['first_paid_at','last_paid_at','last_enquiry_at'],true)){if(!in_array($o,['gte','lte'],true)||!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))$fail();}
   elseif($f==='subscription'){if($o!=='eq'||!in_array($c['channel']??'', ['email','sms','whatsapp'],true)||!in_array($v,['subscribed','unknown','unsubscribed','suppressed'],true))$fail();}
   elseif($f==='source'){if($o!=='eq'||!in_array($v,['customer','order','enquiry','newsletter','import'],true))$fail();}
   elseif($f==='enquiry_status'){if($o!=='eq'||!in_array($v,['new','open','in_progress','quoted','accepted','closed'],true))$fail();}
   elseif($f==='tag'){if($o!=='eq'||!is_string($v)||$v===''||mb_strlen($v)>80)$fail();}
   else $fail();
  }return $r;
 }
 public function apply($q,array $r){$r=$this->validate($r);return $q->where(function($group)use($r){foreach($r['conditions'] as $i=>$c){$method=$r['match']==='any'&&$i>0?'orWhere':'where';$group->$method(fn($b)=>$this->condition($b,$c));}});}
 public function globalSuppression($q):void {$q->whereNotExists(function($s){$s->selectRaw('1')->from('marketing_audience_subscriptions as g')->where('g.merchant_key',app(AudienceDirectory::class)->merchant())->where('g.channel','global')->where('g.status','suppressed')->where(fn($b)=>$b->whereColumn('g.destination_hash','marketing_audience_contacts.email_hash')->orWhereColumn('g.destination_hash','marketing_audience_contacts.phone_hash'));});}
 public function eligible($q,string $ch){$hash=$ch==='email'?'email_hash':'phone_hash';$dest=$ch==='email'?'email':'phone';$q->whereNotNull($dest)->where('conflict',false);$this->globalSuppression($q);return $q->whereExists(fn($s)=>$s->selectRaw('1')->from('marketing_audience_subscriptions as s')->where('s.merchant_key',app(AudienceDirectory::class)->merchant())->where('s.channel',$ch)->where('s.status','subscribed')->whereColumn('s.destination_hash','marketing_audience_contacts.'.$hash));}
 public function condition($q,array $c):void {
  $f=$c['field'];$v=$c['value'];$op=['eq'=>'=','gte'=>'>=','lte'=>'<='][$c['operator']]??null;
  if($f==='birthday_month'){$q->where('birthday_month',(int)$v);return;}
  if($f==='category_purchased'){$q->whereExists(function($s)use($v){$s->selectRaw('1')->from('marketing_audience_sources as paid')->join('order_items as oi','oi.order_id','=','paid.source_id')->join('product_variants as pv','pv.id','=','oi.product_variant_id')->join('products as p','p.id','=','pv.product_id')->whereColumn('paid.contact_id','marketing_audience_contacts.id')->where('paid.merchant_key',app(AudienceDirectory::class)->merchant())->where('paid.kind','order')->where('paid.active',true)->where('paid.paid_minor','>',0)->where('p.category_id',$v);});return;}
  if(in_array($f,['first_paid_at','last_paid_at','last_enquiry_at'],true)&&in_array($c['operator'],['within_last_days','more_than_days_ago','between_days_ago'],true)){
   $newer=now()->subDays((int)$v);$older=now()->subDays((int)($c['value_to']??$v));
   if($f==='first_paid_at'){$sub=DB::table('marketing_audience_sources')->selectRaw('MIN(occurred_at)')->whereColumn('contact_id','marketing_audience_contacts.id')->where('merchant_key',app(AudienceDirectory::class)->merchant())->where('kind','order')->where('active',true)->where('paid_minor','>',0);$q->where($sub,$c['operator']==='within_last_days'?'>=':'<',$newer);if($c['operator']==='between_days_ago')$q->where(clone $sub,'>=',$older);}
   else {$q->whereNotNull($f)->where($f,$c['operator']==='within_last_days'?'>=':'<',$newer);if($c['operator']==='between_days_ago')$q->where($f,'>=',$older);}return;
  }
  if($f==='first_paid_at'){$q->where(DB::table('marketing_audience_sources')->selectRaw('MIN(occurred_at)')->whereColumn('contact_id','marketing_audience_contacts.id')->where('merchant_key',app(AudienceDirectory::class)->merchant())->where('kind','order')->where('active',true)->where('paid_minor','>',0),$op,$v);return;}
  if(in_array($f,['paid_orders','enquiries'],true)){$q->where($f,$op,(int)$v);return;}
  if(in_array($f,['last_paid_at','last_enquiry_at'],true)){$q->whereDate($f,$op,$v);return;}
  if($f==='net_minor'){$q->whereExists(fn($s)=>$s->selectRaw('1')->from('marketing_audience_values')->whereColumn('contact_id','marketing_audience_contacts.id')->where('currency',$c['currency'])->where('net_minor',$op,(int)$v));return;}
  if($f==='tag'){$q->whereJsonContains('tags',$v);return;}
  if(in_array($f,['source','enquiry_status'],true)){$q->whereExists(function($s)use($f,$v){$s->selectRaw('1')->from('marketing_audience_sources')->whereColumn('contact_id','marketing_audience_contacts.id')->where('active',true);if($f==='source')$s->where('kind',$v);else $s->where('kind','enquiry')->where('status',$v);});return;}
  if($f==='subscription'){$ch=$c['channel'];$hash=$ch==='email'?'email_hash':'phone_hash';
   if($v==='subscribed'){$this->eligible($q,$ch);return;}
   if($v==='unknown'){$q->whereNotExists(fn($s)=>$s->selectRaw('1')->from('marketing_audience_subscriptions as s')->where('s.merchant_key',app(AudienceDirectory::class)->merchant())->where('s.channel',$ch)->whereColumn('s.destination_hash','marketing_audience_contacts.'.$hash));$this->globalSuppression($q);return;}
   $q->whereExists(function($s)use($ch,$hash,$v){$s->selectRaw('1')->from('marketing_audience_subscriptions as s')->where('s.merchant_key',app(AudienceDirectory::class)->merchant())->where('s.status',$v)->where(function($b)use($ch,$hash,$v){$b->where(fn($t)=>$t->where('s.channel',$ch)->whereColumn('s.destination_hash','marketing_audience_contacts.'.$hash));if($v==='suppressed')$b->orWhere(fn($t)=>$t->where('s.channel','global')->where(fn($x)=>$x->whereColumn('s.destination_hash','marketing_audience_contacts.email_hash')->orWhereColumn('s.destination_hash','marketing_audience_contacts.phone_hash')));});});
  }
 }
 public function preview($q):array {$total=(clone $q)->count();$out=['matched'=>$total];foreach(['email','sms','whatsapp'] as $ch){$eligible=$this->eligible(clone $q,$ch)->count();$conflict=(clone $q)->where('conflict',true)->count();$missing=(clone $q)->where('conflict',false)->whereNull($ch==='email'?'email':'phone')->count();$out[$ch]=['eligible'=>$eligible,'excluded'=>$total-$eligible,'reasons'=>['identity_conflict'=>$conflict,'missing_destination'=>$missing,'not_subscribed_or_suppressed'=>max(0,$total-$eligible-$conflict-$missing)]];}return $out;}
}
