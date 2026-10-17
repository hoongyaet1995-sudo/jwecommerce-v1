<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/** Replays completed operations after disconnect; never reruns an uncertain in-flight request. */
final class CmsAiTask
{
 public function handle(Request $r,Closure $next){
  $id=$r->input('operationId');if(!$id)return $next($r);
  abort_unless(is_string($id)&&Str::isUuid($id),422,'Invalid AI operation identifier.');
  $user=$r->user()->getAuthIdentifier();$hash=hash('sha256',$r->getContent());$action=$r->input('action');abort_unless(in_array($action,['brief','answer','generate','refine','image'],true),422,'Unsupported tracked operation.');
  $model=$action==='image'?$r->input('imageModel'):\App\Models\AiConnection::where('enabled',true)->where('builder_enabled',true)->value('text_model');
  $inserted=DB::table('cms_ai_tasks')->insertOrIgnore(['id'=>$id,'user_id'=>$user,'action'=>$action,'request_hash'=>$hash,'status'=>'running','model'=>$model,'created_at'=>now(),'updated_at'=>now()]);
  if(!$inserted){$task=DB::table('cms_ai_tasks')->where('id',$id)->where('user_id',$user)->first();abort_unless($task&&hash_equals($task->request_hash,$hash),409,'This operation identifier belongs to a different request.');abort_if($task->status==='running',409,'This request is still processing or awaiting confirmation. Check its status before retrying.');return response()->json(json_decode($task->response),$task->response_status);}
  $r->attributes->remove('ai_provider_usage');
  try{$response=$next($r);$body=json_decode($response->getContent());$usage=$r->attributes->get('ai_provider_usage');if($response->isSuccessful()&&is_object($body->data??null)&&is_array($usage)&&$usage){$body->data->usage=$usage;$response->setContent(json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}DB::table('cms_ai_tasks')->where('id',$id)->update(['status'=>$response->isSuccessful()?'completed':'failed','response'=>$response->getContent(),'response_status'=>$response->getStatusCode(),'updated_at'=>now()]);return $response;}
  catch(\Throwable $e){report($e);$response=app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->render($r,$e);DB::table('cms_ai_tasks')->where('id',$id)->update(['status'=>'failed','response'=>$response->getContent(),'response_status'=>$response->getStatusCode(),'updated_at'=>now()]);return $response;}
 }
}
