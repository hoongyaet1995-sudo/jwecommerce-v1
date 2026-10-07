<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\Support\SupportReplyCadenceService;
class ProcessSupportReplyBatch implements ShouldQueue
{
    use Queueable;
    public int $tries=3;
    public int $timeout=110;
    public function __construct(public string $batchId) {}
    public function handle(SupportReplyCadenceService $service):void {$service->process($this->batchId);}
}
