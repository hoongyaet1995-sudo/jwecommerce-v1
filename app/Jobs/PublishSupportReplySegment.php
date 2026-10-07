<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\Support\SupportReplyCadenceService;
class PublishSupportReplySegment implements ShouldQueue
{
    use Queueable;
    public int $tries=3;
    public int $timeout=30;
    public function __construct(public string $batchId) {}
    public function handle(SupportReplyCadenceService $service):void {$service->publish($this->batchId);}
}
