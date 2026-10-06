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
    public function upload(Request $r, SupportChatService $chat, SupportAttachmentService $media, ?SupportConversation $conversation=null)
    {
        $r->validate(['image'=>['required','file','max:5120']]);
        $admin=$r->is('admin/*');
        if(!$admin){[$owner,$user]=$chat->owner($r);$conversation=SupportConversation::firstOrCreate(['owner_hash'=>$owner],['id'=>(string)Str::uuid(),'customer_user_id'=>$user]);}
        abort_unless($conversation,404);
        abort_unless(in_array($conversation->channel??'web_guest',['web_guest','facebook','instagram','whatsapp'],true),409,'Images are unavailable for this channel.');
        $a=$media->upload($conversation,$r->file('image'),$admin?$r->user()->id:null);
        return response()->json(['attachment'=>['id'=>$a->id,'name'=>$a->name,'url'=>($admin?'/admin/api/support/attachments/':'/support/chat/attachments/').$a->id,'type'=>'image','sizeBytes'=>$a->bytes]],201)->header('Cache-Control','no-store');
    }
    public function provider(Request $r, SupportAttachment $attachment)
    {
        abort_unless($attachment->message_id && $attachment->staff_user_id && $attachment->status==='ready' && $attachment->path,404);
        abort_unless(in_array($attachment->conversation->channel,['facebook','instagram'],true),404);
        return Storage::disk($attachment->disk)->response($attachment->path,$attachment->name,['Content-Type'=>$attachment->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function show(Request $r, SupportAttachment $attachment, SupportChatService $chat)
    {
        if(!$r->is('admin/*')){[$owner]=$chat->owner($r);abort_unless(hash_equals($attachment->owner_hash,$owner),404);}
        abort_unless($attachment->status==='ready' && ($attachment->message_id || $attachment->created_at->gt(now()->subDay())),404);
        abort_unless($attachment->path && Storage::disk($attachment->disk)->exists($attachment->path),404);
        return Storage::disk($attachment->disk)->response($attachment->path,$attachment->name,['Content-Type'=>$attachment->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'"],$r->boolean('download')?'attachment':'inline');
    }
}
