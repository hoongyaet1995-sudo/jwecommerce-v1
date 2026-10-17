<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class CmsAiWorkspaceController extends Controller
{
 private function query(Request $r,string $page){abort_unless(preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$page),404);return DB::table('cms_ai_workspaces')->where('user_id',$r->user()->getAuthIdentifier())->where('source_page',$page);}
 public function show(Request $r,string $page){$w=$this->query($r,$page)->first();return response()->json(['version'=>$w?->version??0,'data'=>$w&&!$w->discarded_at&&$w->updated_at>=now()->subDays(7)->toDateTimeString()?['signature'=>$w->source_signature,'state'=>$this->restoreState($w->state)]:null]);}
 public function save(Request $r,string $page){
  $input=$r->validate(['version'=>'required|integer|min:0','signature'=>'required|string|max:1000000','state'=>'required|array','state.working'=>'required|array','state.step'=>'required|integer|min:0|max:4','state.mode'=>'required|in:create,improve,section']);
  $input['state']=$r->input('state');
  abort_if(strlen(json_encode($input['state']))>3*1024*1024,422,'This workspace is too large. Apply it to a CMS draft before continuing.');
  $raw=json_decode($r->getContent());abort_unless(($raw->state??null) instanceof \stdClass&&($raw->state->working??null) instanceof \stdClass,422,'Workspace and document must be JSON objects.');app(\App\Domain\Cms\PageDocumentContract::class)->validate(app(\App\Domain\Cms\PageDocumentJsonCodec::class)->normalizeDocumentToken($raw->state->working));
  return DB::transaction(function()use($r,$page,$input,$raw){$q=$this->query($r,$page);$w=$q->lockForUpdate()->first();abort_if(($w?->version??0)!==$input['version'],409,'This AI workspace changed in another tab. Reopen it before editing.');$version=($w?->version??0)+1;$values=['version'=>$version,'source_signature'=>$input['signature'],'state'=>json_encode($raw->state),'discarded_at'=>null,'updated_at'=>now()];if($w)$q->update($values);else DB::table('cms_ai_workspaces')->insert($values+['user_id'=>$r->user()->getAuthIdentifier(),'source_page'=>$page,'created_at'=>now()]);return response()->json(['version'=>$version]);});
 }
 public function discard(Request $r,string $page){$input=$r->validate(['version'=>'required|integer|min:0']);return DB::transaction(function()use($r,$page,$input){$q=$this->query($r,$page);$w=$q->lockForUpdate()->first();abort_if(($w?->version??0)!==$input['version'],409,'This workspace changed in another tab. Reopen it before discarding.');if($w)$q->update(['discarded_at'=>now(),'version'=>$w->version+1,'updated_at'=>now()]);return response()->json(['version'=>($w?->version??0)+1]);});}
 public function task(Request $r,string $id){$t=DB::table('cms_ai_tasks')->where('id',$id)->where('user_id',$r->user()->getAuthIdentifier())->first();abort_unless($t,404);return response()->json(['data'=>['id'=>$t->id,'action'=>$t->action,'status'=>$t->status,'model'=>$t->model,'response'=>$t->response?json_decode($t->response):null,'responseStatus'=>$t->response_status,'updatedAt'=>$t->updated_at]]);}
 private function restoreState(string $json):object{
  $state=json_decode($json);$codec=app(\App\Domain\Cms\PageDocumentJsonCodec::class);
  foreach(['working','proposal'] as $field)if(isset($state->$field))$state->$field=json_decode($codec->encodeDocument(json_decode(json_encode($state->$field),true)));
  if(isset($state->history))foreach($state->history as &$document)$document=json_decode($codec->encodeDocument(json_decode(json_encode($document),true)));
  if(isset($state->pending->document))$state->pending->document=json_decode($codec->encodeDocument(json_decode(json_encode($state->pending->document),true)));
  return $state;
 }
}
