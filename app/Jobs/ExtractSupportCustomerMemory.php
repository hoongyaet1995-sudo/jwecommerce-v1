<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ExtractSupportCustomerMemory implements ShouldQueue {
    use Queueable;
    public int $tries=1;public int $timeout=60;
    public function __construct(public int $messageId){$this->onConnection('support')->onQueue('support-ai');}
    public function handle():void {
        $m=\App\Models\SupportMessage::find($this->messageId);if(!$m)return;
        $c=\App\Models\SupportConversation::find($m->conversation_id);if($c)app(\App\Services\Support\SupportCustomerMemoryService::class)->capture($c,$m);
    }
}
