<?php
namespace App\Services;

use App\Models\{ProductEnquiry,SupportConversation,User};
use App\Services\Support\SupportMessageAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Notification};
use Illuminate\Support\Str;

class ProductEnquirySupportService
{
    public function connect(Request $request,ProductEnquiry $enquiry):SupportConversation
    {
        abort_unless(app(ProductEnquiryService::class)->canView($request,$enquiry),404);
        $alreadyLinked=(bool)$enquiry->support_conversation_id;
        $settings=app(SupportFaqService::class)->read();$ai=app(\App\Services\Support\EnquiryReception::class)->settings($settings)['isEnabled']&&app(\App\Services\Support\SupportAiPolicy::class)->enabled($settings,'web_guest');
        $conversation=DB::transaction(function()use($enquiry,$ai){
            $row=ProductEnquiry::whereKey($enquiry->id)->lockForUpdate()->firstOrFail();
            if($row->support_conversation_id)return SupportConversation::findOrFail($row->support_conversation_id);
            $chat=SupportConversation::create(['id'=>(string)Str::uuid(),'owner_hash'=>hash('sha256','enquiry:'.$row->id),'customer_user_id'=>$row->customer_user_id,'status'=>$ai?'open':'waiting_human','last_message_at'=>now()]);
            $snapshot=$row->product_snapshot;
            $content="Enquiry {$row->reference}\n{$row->product_title}";
            if(!empty($snapshot['variant']))$content.="\nOption: ".$snapshot['variant'];
            if(!empty($snapshot['sku']))$content.="\nSKU: ".$snapshot['sku'];
            $content.="\n\n".$row->requirements;
            $message=$chat->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>$content]);
            $chat->notes()->create(['content'=>'[Contact] '.json_encode(['name'=>$row->name,'email'=>$row->email,'phone'=>$row->phone],JSON_UNESCAPED_UNICODE)]);
            $chat->notes()->create(['content'=>'[Control] enquiry → '.($ai?'open (AI reception)':'waiting_human (AI access off)')]);
            $row->support_conversation_id=$chat->id;$row->version++;$row->save();
            $row->events()->create(['type'=>'support_linked','content'=>'Customer connected this enquiry to Support Inbox.','changes'=>['support_conversation_id'=>['from'=>null,'to'=>$chat->id]]]);
            app(SupportMessageAlertService::class)->received($chat,$message);
            if(!$ai)User::where('is_admin',true)->chunkById(100,fn($staff)=>Notification::send($staff,new \App\Notifications\SupportHumanRequested($chat->id,'Enquiry follow-up requested','A customer has connected an enquiry to Support Inbox.')));
            return $chat;
        },3);
        DB::transaction(function()use($enquiry,$conversation){ProductEnquiry::whereKey($enquiry->id)->lockForUpdate()->firstOrFail();app(EnquiryQuotationService::class)->deliverPending($enquiry,$conversation);},3);
        $request->session()->put('support_enquiry_id',$enquiry->id);
        if(!$alreadyLinked&&$ai){$source=$conversation->messages()->where('sender_role','customer')->oldest('id')->first();if($source)\App\Jobs\ProcessEnquiryReception::dispatch($conversation->id,$source->client_message_id)->afterResponse();}
        return $conversation;
    }
}
