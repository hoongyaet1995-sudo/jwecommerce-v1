<?php
namespace App\Http\Controllers;
use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Services\Support\SupportAttachmentService;
use App\Services\Support\SupportChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportAttachmentController extends Controller
{
    public function retryAnalysis(Request $r,SupportAttachment $attachment,\App\Services\Support\SupportAttachmentAnalysisService $analysis)
    {
        abort_unless($r->user()?->is_admin,403);
        abort_unless($attachment->merchant_key===null || $attachment->merchant_key===config('services.facebook.merchant_key','jw-ecommerce'),404);
        $analysis->retry($attachment,$r->user()->id);
        return response()->json(['status'=>'pending'])->header('Cache-Control','no-store');
    }
    public function upload(Request $r, SupportChatService $chat, SupportAttachmentService $media, ?SupportConversation $conversation=null)
    {
        abort_if(!$r->is('admin/*') && $r->hasFile('document'),422,'PDF uploads are not available in website chat.');
        $r->validate(['image'=>['nullable','file','max:5120'],'audio'=>['nullable','file','max:16384'],'document'=>['nullable','file','max:10240']]);
        abort_unless(count(array_filter(['image','audio','document'],fn($key)=>$r->hasFile($key)))===1,422,'Attach one file at a time.');
        $admin=$r->is('admin/*');
        if(!$admin){[$owner,$user]=$chat->owner($r);$conversation=SupportConversation::firstOrCreate(['owner_hash'=>$owner],['id'=>(string)Str::uuid(),'customer_user_id'=>$user]);}
        abort_unless($conversation,404);
        abort_unless(in_array($conversation->channel??'web_guest',['web_guest','facebook','instagram','whatsapp'],true),409,'Images are unavailable for this channel.');
        abort_if($r->hasFile('audio') && (!$admin || $conversation->channel!=='whatsapp'),422,'Audio is available only for WhatsApp staff replies.');
        abort_if($r->hasFile('document')&&!app(\App\Services\Support\SupportMediaCapabilities::class)->forChannel($conversation->channel??'web_guest')['documents'],422,'Documents are unavailable for this channel.');
        $a=$r->hasFile('document')?$media->uploadDocument($conversation,$r->file('document'),$admin?$r->user()->id:null):($r->hasFile('audio')?$media->uploadAudio($conversation,$r->file('audio'),$r->user()->id):$media->upload($conversation,$r->file('image'),$admin?$r->user()->id:null));
        return response()->json(['attachment'=>['id'=>$a->id,'name'=>$a->name,'url'=>($admin?'/admin/api/support/attachments/':'/support/chat/attachments/').$a->id,'type'=>$a->kind ?? 'image','durationSeconds'=>$a->duration_seconds,'sizeBytes'=>$a->bytes]],201)->header('Cache-Control','no-store');
    }
    public function retry(Request $r,SupportAttachment $attachment,\App\Services\Support\SupportAttachmentRetry $retry) {
        $retry->retry($attachment);return response()->json(['status'=>'pending'])->header('Cache-Control','no-store');
    }
    public function provider(Request $r, SupportAttachment $attachment)
    {
        abort_unless($attachment->message_id && $attachment->staff_user_id && $attachment->status==='ready' && $attachment->path,404);
        abort_unless(in_array($attachment->conversation->channel,['facebook','instagram'],true),404);
        abort_if($attachment->kind==='document'&&$attachment->conversation->channel!=='facebook',404);
        return Storage::disk($attachment->disk)->response($attachment->path,$attachment->name,['Content-Type'=>$attachment->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'],$attachment->kind==='document'?'attachment':'inline');
    }
    public function show(Request $r, SupportAttachment $attachment, SupportChatService $chat)
    {
        if(!$r->is('admin/*')){[$owner]=$chat->owner($r);abort_unless(hash_equals($attachment->owner_hash,$owner),404);}
        abort_unless($attachment->status==='ready' && ($attachment->message_id || $attachment->created_at->gt(now()->subDay())),404);
        abort_unless($attachment->path && Storage::disk($attachment->disk)->exists($attachment->path),404);
        $headers=['Content-Type'=>$attachment->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'"];
        if($attachment->kind==='audio'){
            $response=response()->file(Storage::disk($attachment->disk)->path($attachment->path),$headers);
            return $response->setContentDisposition($r->boolean('download')?'attachment':'inline',$attachment->name);
        }
        return Storage::disk($attachment->disk)->response($attachment->path,$attachment->name,$headers,($r->boolean('download')||$attachment->kind==='document')?'attachment':'inline');
    }
}
