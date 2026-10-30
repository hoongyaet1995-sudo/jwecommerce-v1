<?php
namespace App\Services\Marketing\Audience;
use App\Domain\Marketing\MarketingPermission as Permission;
use App\Models\{User,Customer,Order};
use App\Services\Customers\ContactValueNormalizer as Normalize;
use Illuminate\Support\Facades\{DB,Storage,Crypt,Log};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AudienceTasks {
 public function directory():AudienceDirectory{return app(AudienceDirectory::class);}
 public function audit(int $user,string $action,?string $id=null,array $meta=[]):void{DB::table('marketing_audience_audits')->insert(['merchant_key'=>$this->directory()->merchant(),'user_id'=>$user,'action'=>$action,'record_id'=>$id,'metadata'=>json_encode($meta),'created_at'=>now()]);}
 public function start(User $u,string $kind,array $payload=[]):string {
  return DB::transaction(function()use($u,$kind,$payload){$merchant=$this->directory()->merchant();DB::table('marketing_audience_locks')->insertOrIgnore(['merchant_key'=>$merchant]);DB::table('marketing_audience_locks')->where('merchant_key',$merchant)->lockForUpdate()->first();
   if($kind==='sync'){$old=DB::table('marketing_audience_tasks')->where('merchant_key',$merchant)->where('kind','sync')->where('state','queued')->first();if($old)return $old->id;}
   $id=(string)Str::uuid();DB::table('marketing_audience_tasks')->insert(['id'=>$id,'merchant_key'=>$merchant,'user_id'=>$u->id,'kind'=>$kind,'state'=>'queued','payload'=>json_encode($payload),'created_at'=>now(),'updated_at'=>now(),'expires_at'=>now()->addDay()]);if($kind==='export'){$members=$this->exportQuery($payload)->selectRaw('? as task_id, marketing_audience_contacts.id as contact_id',[$id])->limit(50001);DB::table('marketing_audience_export_members')->insertUsing(['task_id','contact_id'],$members);abort_if(DB::table('marketing_audience_export_members')->where('task_id',$id)->count()>50000,422,'Narrow your selection to 50,000 contacts or fewer.');$payload['snapshot_id']=$id;DB::table('marketing_audience_tasks')->where('id',$id)->update(['payload'=>json_encode($payload)]);}$this->audit($u->id,$kind.'_queued',$id);return $id;
  });
 }
 public function checkpoint($t):string {return hash('sha256',$t->id.'|'.$t->state.'|'.$t->processed.'|'.$t->payload);}
 public function step(string $id,bool $pageDriven=false,?string $checkpoint=null):void {
  try{DB::transaction(function()use($id,$pageDriven,$checkpoint){$t=DB::table('marketing_audience_tasks')->where('id',$id)->lockForUpdate()->first();if(!$t||$t->state!=='queued')return;if(!$pageDriven)return;if($t->kind!=='sync'&&now()->greaterThan($t->expires_at))return;if($checkpoint!==null&&!hash_equals($this->checkpoint($t),$checkpoint))return;
   $u=User::find($t->user_id);$permission=$t->kind==='export'?Permission::EXPORT_AUDIENCE:Permission::MANAGE_AUDIENCE;
   if(!Permission::allows($u,Permission::VIEW_AUDIENCE)||!Permission::allows($u,$permission))throw new \RuntimeException('permission_revoked');
   if($t->merchant_key!==$this->directory()->merchant())throw new \RuntimeException('store_scope_changed');
   DB::table('marketing_audience_locks')->where('merchant_key',$t->merchant_key)->lockForUpdate()->first();$d=json_decode($t->payload,true);
   if($t->kind==='sync')$this->sync($t,$d);elseif($t->kind==='import')$this->import($t,$d);elseif($t->kind==='export')$this->export($t,$d);else throw new \RuntimeException('unsupported_task');

  });}catch(\Throwable $e){DB::table('marketing_audience_tasks')->where('id',$id)->update(['state'=>'failed','error_code'=>in_array($e->getMessage(),['permission_revoked','row_limit','export_limit','file_unavailable','store_scope_changed','export_file_incomplete'],true)?$e->getMessage():'audience_task_failed','updated_at'=>now()]);Log::warning('Audience task paused',['task_id'=>$id,'exception_type'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);return;}
 }
 private function update($t,array $d,array $values=[]):void {DB::table('marketing_audience_tasks')->where('id',$t->id)->update(array_merge(['payload'=>json_encode($d),'updated_at'=>now()],$values));}
 private function sync($t,array $d):void {
  $stages=['customer','order','enquiry','newsletter','whatsapp','retire','totals'];$stage=$d['stage']??0;$kind=$stages[$stage];$after=$d['after']??null;$dir=$this->directory();
  if($kind==='retire'){
   $rows=DB::table('marketing_audience_sources')->where('merchant_key',$t->merchant_key)->whereIn('kind',['customer','order','enquiry','newsletter'])->where('active',true)->where(fn($b)=>$b->where('run_id','!=',$t->id)->orWhereNull('run_id'))->limit(100)->get();
   foreach($rows as $r){DB::table('marketing_audience_sources')->where('id',$r->id)->update(['active'=>false]);$dir->recalculate($r->contact_id);}if($rows->isEmpty()){$d=['stage'=>$stage+1];}$this->update($t,$d);return;
  }
  if($kind==='totals'){
   $rows=$dir->query()->when($after,fn($q)=>$q->where('id','>',$after))->orderBy('id')->limit(100)->get();foreach($rows as $r){$dir->recalculate($r->id);$d['after']=$r->id;}$this->update($t,$d,$rows->isEmpty()?['state'=>'completed']:[]);return;
  }
  $tables=['customer'=>'customers','order'=>'orders','enquiry'=>'product_enquiries','newsletter'=>'newsletter_subscriptions','whatsapp'=>'marketing_whatsapp_subscriptions'];
  $q=DB::table($tables[$kind]);if($kind==='customer')$q->whereNull('merged_into_id');if($kind==='newsletter')$q->where('document_stage','published');if($kind==='whatsapp')$q->where('merchant_key',$t->merchant_key);
  $rows=$q->when($after!==null,fn($q)=>$q->where('id','>',$after))->orderBy('id')->limit(100)->get();
  $customers=$kind==='customer'?Customer::with('user')->whereIn('id',$rows->pluck('id'))->get()->keyBy('id'):collect();
  $orders=$kind==='order'?Order::with(['customer','payments','refunds'])->whereIn('id',$rows->pluck('id'))->get()->keyBy('id'):collect();
  $events=$kind==='order'?\App\Models\PaymentWebhookEvent::whereIn('order_payment_id',$orders->flatMap(fn($o)=>$o->payments->pluck('id')))->whereNotNull('verified_at')->get():collect();
  foreach($rows as $r){$data=[];
   if($kind==='customer')$data=['name'=>trim($r->first_name.' '.$r->last_name)?:($customers[$r->id]?->user?->name??'Customer'),'email'=>$r->email,'phone'=>$r->phone,'customer_id'=>$r->id,'birthday_month'=>$r->date_of_birth?(int)substr($r->date_of_birth,5,2):null,'tags'=>json_decode($r->tags??'[]',true)??[]];
   elseif($kind==='order'){$o=$orders[$r->id];$c=$o->customer;$data=['name'=>$c?trim($c->first_name.' '.$c->last_name):'Guest customer','email'=>$o->email?:$c?->email,'phone'=>$c?->phone]+app(AudiencePaymentEvidence::class)->paid($o,$events);}
   elseif($kind==='enquiry')$data=['name'=>$r->name,'email'=>$r->email,'phone'=>$r->phone,'status'=>$r->status,'occurred_at'=>$r->created_at];
   elseif($kind==='newsletter'){$data=['name'=>'Newsletter subscriber','email'=>$r->email];$email=Normalize::email($r->email);if($email)$dir->subscribe('email',$email,'subscribed','newsletter:'.$r->id,$r->created_at);}
   elseif($kind==='whatsapp'){try{$phone=Normalize::phone(Crypt::decryptString($r->encrypted_phone));}catch(\Throwable){$phone=null;}if($phone&&in_array($r->status,['subscribed','unsubscribed','suppressed'],true)&&($r->status!=='subscribed'||$r->consented_at))$dir->subscribe('whatsapp',$phone,$r->status,'whatsapp:'.$r->id,$r->revoked_at??$r->consented_at??$r->updated_at);}
   if($kind!=='whatsapp')$dir->project($kind,(string)$r->id,$data,$t->id);$d['after']=$r->id;
  }
  $this->update($t,$rows->isEmpty()?['stage'=>$stage+1]:$d,['processed'=>$t->processed+$rows->count()]);
 }
 public function validateRow(array $d):?string {
  if(!mb_check_encoding(implode('',array_values($d)),'UTF-8'))return 'Use a UTF-8 CSV file.';
  if(mb_strlen($d['tags']??'')>1000)return 'Use up to 1,000 characters for tags.';$tags=array_values(array_filter(array_map('trim',explode(';',$d['tags']??''))));if(count($tags)>20||collect($tags)->contains(fn($tag)=>mb_strlen($tag)>80))return 'Use up to 20 tags, each with 80 characters or fewer.';
  if(mb_strlen($d['name']??'')>255||mb_strlen($d['email']??'')>254||mb_strlen($d['phone']??'')>40)return 'A field is too long.';
  $email=Normalize::email($d['email']??null);$phone=Normalize::phone($d['phone']??null);
  if(!empty($d['email'])&&!$email)return 'Email address is invalid.';if(!empty($d['phone'])&&!$phone)return 'Use an international phone number starting with +.';if(!$email&&!$phone)return 'Provide an email or international phone number.';
  $matches=$this->directory()->query()->where(fn($q)=>$q->when($email,fn($q)=>$q->where('email',$email))->when($phone,fn($q)=>$q->orWhere('phone',$phone)))->get();
  if($matches->count()>1)return 'Email and phone match different contacts.';
  $c=$matches->first();if($c&&($c->conflict||($email&&$c->email&&$email!==$c->email)||($phone&&$c->phone&&$phone!==$c->phone)))return 'Contact identifiers conflict; no update applied.';
  return null;
 }
 private function import($t,array $d):void {
  if(($d['stage']??'preview')==='apply'){
   $rows=DB::table('marketing_audience_import_rows')->where('task_id',$t->id)->where('status','valid')->orderBy('id')->limit(100)->get();$rejected=0;foreach($rows as $row){$data=json_decode($row->data,true);$error=$this->validateRow($data);if($error)$rejected++;$data['tags']=array_values(array_filter(array_map('trim',explode(';',$data['tags']??''))));if(!$error)$this->directory()->project('import',$t->id.':'.$row->row_number,$data,$t->id);DB::table('marketing_audience_import_rows')->where('id',$row->id)->update(['status'=>$error?'invalid':'imported','error'=>$error]);}
   $this->update($t,$d,['processed'=>$t->processed+$rows->count(),'invalid'=>$t->invalid+$rejected,'valid'=>$t->valid-$rejected,'state'=>$rows->isEmpty()?'completed':'queued']);return;
  }
  $h=Storage::disk('local')->readStream($d['path']);if(!$h)throw new \RuntimeException('file_unavailable');
  try{if($t->cursor)fseek($h,$t->cursor);else {fgetcsv($h,0,',','"','');}$n=0;$valid=0;$invalid=0;$line=$d['line']??1;
   while($n<100&&($row=fgetcsv($h,0,',','"',''))!==false){$line++;if($line>5001)throw new \RuntimeException('row_limit');$data=[];foreach(['name','email','phone','tags'] as $field){$index=array_search($d['mapping'][$field]??'', $d['headers'],true);$data[$field]=$index===false?'':trim($row[$index]??'');}
    $error=$this->validateRow($data);if(!$error){$duplicate=DB::table('marketing_audience_import_rows')->where('task_id',$t->id)->where('status','valid')->where('data',json_encode($data))->exists();if($duplicate)$error='Duplicate row in this import.';}
    DB::table('marketing_audience_import_rows')->updateOrInsert(['task_id'=>$t->id,'row_number'=>$line],['data'=>json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE),'status'=>$error?'invalid':'valid','error'=>$error]);$error?$invalid++:$valid++;$n++;
   }$cursor=ftell($h);$done=feof($h);$d['line']=$line;$this->update($t,$d,['cursor'=>$cursor,'processed'=>$t->processed+$n,'valid'=>$t->valid+$valid,'invalid'=>$t->invalid+$invalid,'state'=>$done?'preview':'queued']);
  }finally{fclose($h);}
 }
 public function exportQuery(array $d){$q=$this->directory()->applyFilters($this->directory()->query(),$d['filters']??[]);if(!empty($d['segment'])){$s=DB::table('marketing_audience_segments')->where('merchant_key',$this->directory()->merchant())->whereNull('archived_at')->find($d['segment']);abort_unless($s,422);$q=app(AudienceSegments::class)->apply($q,json_decode($s->rules,true));}return $q;}
 private function export($t,array $d):void {
  $q=isset($d['snapshot_id'])?$this->directory()->query()->whereIn('id',DB::table('marketing_audience_export_members')->select('contact_id')->where('task_id',$t->id)):$this->exportQuery($d);$after=$d['after']??null;$rows=$q->when($after,fn($q)=>$q->where('id','>',$after))->orderBy('id')->limit(100)->get();$path='marketing-audience/'.$t->id.'/part-'.str_pad((string)$t->processed,8,'0',STR_PAD_LEFT).'.csv';$h=fopen('php://temp','w+');
  foreach($rows as $r){if($t->processed+$rows->count()>50000)throw new \RuntimeException('export_limit');$fields=[];foreach($d['columns']??['name','email','phone','paid_orders','enquiries'] as $column)$fields[]=$r->$column;foreach($fields as &$v){$v=(string)($v??'');if(preg_match('/^[\s]*[=+@-]/u',$v))$v="'".$v;}unset($v);fputcsv($h,$fields,',','"','');}
  if($rows->isNotEmpty()){$bytes=ftell($h);rewind($h);$checksum=hash('sha256',stream_get_contents($h));rewind($h);if(!Storage::disk('local')->put($path,$h))throw new \RuntimeException('export_file_incomplete');$d['parts'][]=['path'=>$path,'bytes'=>$bytes,'sha256'=>$checksum,'rows'=>$rows->count()];$d['after']=$rows->last()->id;$this->update($t,$d,['processed'=>$t->processed+$rows->count()]);}else {$this->verifyExportParts($t->id,$d,$t->processed);$this->update($t,$d,['state'=>'completed','expires_at'=>now()->addDay()]);}fclose($h);
 }
 public function verifyExportParts(string $id,array $d,int $expectedRows):array {
  $paths=[];$rows=0;foreach($d['parts']??[] as $part){$path=$part['path'];if(!preg_match('~^marketing-audience/'.preg_quote($id,'~').'/part-[0-9]{8}\.csv$~D',$path))throw new \RuntimeException('export_file_incomplete');$h=Storage::disk('local')->readStream($path);if(!$h)throw new \RuntimeException('export_file_incomplete');$hash=hash_init('sha256');$bytes=hash_update_stream($hash,$h);fclose($h);if($bytes!==$part['bytes']||!hash_equals($part['sha256'],hash_final($hash)))throw new \RuntimeException('export_file_incomplete');$paths[]=$path;$rows+=(int)($part['rows']??0);}if($rows!==$expectedRows)throw new \RuntimeException('export_file_incomplete');return $paths;
 }
 public function confirm(string $id):void{DB::transaction(function()use($id){$t=DB::table('marketing_audience_tasks')->where('id',$id)->lockForUpdate()->firstOrFail();if($t->state==='completed'||(json_decode($t->payload,true)['stage']??null)==='apply')return;abort_unless($t->kind==='import'&&$t->state==='preview',409,'Wait for the import preview.');abort_if(now()->greaterThan($t->expires_at),410,'Upload the file again.');$d=json_decode($t->payload,true);$d['stage']='apply';$this->update($t,$d,['state'=>'queued','processed'=>0]);$this->audit($t->user_id,'import_confirmed',$id);});}
 public function prune():int {$n=0;DB::table('marketing_audience_tasks')->where('created_at','<',now()->subDays(7))->where('state','!=','expired')->orderBy('id')->chunkById(100,function($rows)use(&$n){foreach($rows as $r){Storage::disk('local')->deleteDirectory('marketing-audience/'.$r->id);$d=json_decode($r->payload,true);if(isset($d['path']))Storage::disk('local')->delete($d['path']);DB::table('marketing_audience_import_rows')->where('task_id',$r->id)->delete();DB::table('marketing_audience_export_members')->where('task_id',$r->id)->delete();DB::table('marketing_audience_tasks')->where('id',$r->id)->update(['state'=>'expired','payload'=>'{}','updated_at'=>now()]);$n++;}});return $n;}
}
