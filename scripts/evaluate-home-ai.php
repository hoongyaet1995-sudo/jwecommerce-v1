<?php
/** Local acceptance only. Synthetic chat writes roll back; no social message dispatch. */
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Notification};
use App\Services\Support\{FaqKnowledgeSearch,FaqAutoReplyService,SupportChatService};
if(!app()->environment('local')||str_replace('\\','/',base_path())!=='C:/xampp/htdocs/jwstudio-ecommerce'||DB::connection()->getDatabaseName()!=='jw_ecommerce')throw new RuntimeException('Canonical local acceptance only');
$suite=json_decode(file_get_contents(database_path('fixtures/jw-home-ai-acceptance.json')),true,512,JSON_THROW_ON_ERROR);
$settings=app(App\Services\SupportFaqService::class)->read();$settings['aiChannels']['storefront']=true;
$phase=$argv[1]??'retrieval';$mode=$argv[2]??'semantic';if(!in_array($phase,['retrieval','dialogue'],true)||!in_array($mode,['strict','semantic','deep'],true))throw new RuntimeException('Invalid phase/mode');
$settings['faq']['retrievalDepth']=$mode;
$app->instance(App\Services\SupportFaqService::class,new class($settings) extends App\Services\SupportFaqService{public function __construct(private array $s){}public function read():array{return $this->s;}});
$request=Illuminate\Http\Request::create('/','GET');$request->setLaravelSession(new Illuminate\Session\Store('qa-home-currency',new Illuminate\Session\ArraySessionHandler(120)));$app->instance('request',$request);Notification::fake();
$only=isset($argv[3])?explode(',',$argv[3]):[];
$out=base_path('tmp/home-ai-'.$phase.'-'.$mode.'-'.date('Ymd-His').'.jsonl');file_put_contents($out,'');$results=[];$start=microtime(true);echo 'Evidence: '.$out."\n";
foreach($suite[$phase] as $case){$t=microtime(true);$result=['id'=>$case['id'],'mode'=>$mode];
 if($only&&!in_array($case['id'],$only,true))continue;
 try{
 if($phase==='retrieval'){
  $facts=app(FaqKnowledgeSearch::class)->search($case['q'],$settings['faq']);$ids=array_column($facts,'id');$expected=array_map(fn($n)=>sprintf('jw-home-test-%02d',$n),$case['faq']);
  $result+=['question'=>$case['q'],'expected'=>$expected,'ids'=>$ids,'pass'=>!array_diff($expected,$ids),'primary_hit'=>(bool)array_intersect($expected,array_slice($ids,0,3))];
 }else{
  DB::beginTransaction();try{
   $req=Illuminate\Http\Request::create('/support/chat/messages','POST');$req->setLaravelSession(new Illuminate\Session\Store('qa-'.Illuminate\Support\Str::uuid(),new Illuminate\Session\ArraySessionHandler(120)));$turns=[];
   foreach($case['turns'] as $turn){$data=['client_message_id'=>(string)Illuminate\Support\Str::uuid(),'content'=>$turn['q']];$conv=app(SupportChatService::class)->sendCustomer($req,$data);app(FaqAutoReplyService::class)->reply($conv,$data);$reply=$conv->messages()->latest('id')->first();$answer=$reply->sender_role==='ai'?$reply->content:'';$violations=[];
    foreach($turn['must']??[] as $pattern)if(!preg_match('~'.$pattern.'~iu',$answer))$violations[]='missing:'.$pattern;
    foreach($turn['forbid']??[] as $pattern)if(preg_match('~'.$pattern.'~iu',$answer))$violations[]='forbidden:'.$pattern;
    if($reply->sender_role!=='ai')$violations[]='no AI reply';$turns[]=['question'=>$turn['q'],'answer'=>$answer,'actions'=>$reply->actions,'violations'=>$violations];
   }$result+=['turns'=>$turns,'pass'=>!collect($turns)->contains(fn($r)=>count($r['violations'])>0)];
  }finally{DB::rollBack();}
 }
 }catch(Throwable $e){$result+=['pass'=>false,'error_type'=>get_class($e),'error'=>$e->getMessage()];if($e instanceof Illuminate\Validation\ValidationException||$e instanceof App\Services\AI\ProviderFailure){file_put_contents($out,json_encode($result,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND);throw $e;}}
 $result['seconds']=round(microtime(true)-$t,2);$results[]=$result;file_put_contents($out,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND);echo $case['id'].' '.($result['pass']?'PASS':'REVIEW').' '.$result['seconds']."s\n";flush();
}
echo json_encode(['phase'=>$phase,'mode'=>$mode,'cases'=>count($results),'passed'=>count(array_filter($results,fn($r)=>$r['pass'])),'primary_hits'=>$phase==='retrieval'?count(array_filter($results,fn($r)=>$r['primary_hit']??false)):null,'seconds'=>round(microtime(true)-$start,2)])."\n";
