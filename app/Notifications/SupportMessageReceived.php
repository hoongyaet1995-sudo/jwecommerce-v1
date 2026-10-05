<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class SupportMessageReceived extends Notification
{
    public function __construct(public string $conversationId, public int $messageId, public string $source) {}
    public function via(object $notifiable): array {return ['database'];}
    public function toDatabase(object $notifiable): array {
        return ['conversation_id'=>$this->conversationId,'message_id'=>$this->messageId,
            'title'=>'New customer message','description'=>'A customer sent a new message via '.$this->source.'.'];
    }
}
