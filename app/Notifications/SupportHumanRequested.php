<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SupportHumanRequested extends Notification
{
    public function __construct(public string $conversationId, public string $title='Human support requested', public string $description='A storefront customer is waiting for a support agent.') {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return ['conversation_id'=>$this->conversationId,'title'=>$this->title,'description'=>$this->description];
    }
}
