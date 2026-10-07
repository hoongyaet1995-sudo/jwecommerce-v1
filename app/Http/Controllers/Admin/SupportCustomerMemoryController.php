<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{SupportConversation,SupportCustomerMemory};
use App\Services\Support\SupportCustomerMemoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SupportCustomerMemoryController extends Controller
{
    public function index(SupportConversation $conversation,SupportCustomerMemoryService $service){return response()->json(['memories'=>array_map(fn($r)=>['id'=>$r->id,'kind'=>$r->kind,'text'=>$r->content,'sourceConversationId'=>$r->source_conversation_id,'expiresAt'=>$r->expires_at->toIso8601String()],$service->eligible($conversation))])->header('Cache-Control','no-store');}
    public function update(Request $request,SupportConversation $conversation,SupportCustomerMemory $memory,SupportCustomerMemoryService $service){
        $data=$request->validate(['text'=>'required|string|max:240']);abort_unless($service->safe($data['text']),422,'Use a short preference or request without personal details.');
        return $this->change($request,$conversation,$memory,$service,['content'=>$data['text'],'staff_user_id'=>$request->user()->id],'edited');
    }
    public function destroy(Request $request,SupportConversation $conversation,SupportCustomerMemory $memory,SupportCustomerMemoryService $service){return $this->change($request,$conversation,$memory,$service,['content'=>null,'revoked_at'=>now(),'staff_user_id'=>$request->user()->id],'deleted');}
    private function change(Request $request,SupportConversation $c,SupportCustomerMemory $r,SupportCustomerMemoryService $s,array $data,string $action){
        DB::transaction(function()use($request,$c,$r,$s,$data,$action){
            $c=SupportConversation::whereKey($c->id)->lockForUpdate()->firstOrFail();
            abort_unless(collect($s->eligible($c))->contains(fn($item)=>$item->id===$r->id),404);
            $r->update($data);$c->notes()->create(['staff_user_id'=>$request->user()->id,'content'=>'[Memory] '.$r->id.' '.$action]);$c->touch();
        });return $this->index($c,$s);
    }
}
