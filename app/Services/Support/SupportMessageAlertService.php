<?php
namespace App\Services\Support;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportMessageReceived;
use Illuminate\Support\Facades\Notification;
class SupportMessageAlertService
{
    // Called inside the accepted message transaction, after replay checks.
    public function received(SupportConversation $conversation, SupportMessage $message): void {
        if($message->sender_role!=='customer')return;
        $source=in_array($conversation->channel,['facebook','instagram','whatsapp'],true)
            ? (match($conversation->channel){'instagram'=>'Instagram · ','whatsapp'=>'WhatsApp · ',default=>'Facebook · '}).($conversation->provider_page_name?:$conversation->provider_page_id)
            : 'Storefront';
        User::where('is_admin',true)->chunkById(100,fn($staff)=>Notification::send($staff,
            new SupportMessageReceived($conversation->id,$message->id,$source)));
    }
}
