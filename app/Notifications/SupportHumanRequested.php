<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SupportHumanRequested extends Notification
{
    public function __construct(public string $conversationId) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return ['conversation_id'=>$this->conversationId,'title'=>'Human support requested','description'=>'A storefront customer is waiting for a support agent.'];
    }
}
