<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\Marketing\MarketingPostQueue;
class PublishMarketingPost implements ShouldQueue {
 use Queueable;
 public int $tries=1;
 public int $timeout=600;
 public bool $failOnTimeout=true;
 public function __construct(public string $dispatchId){$this->onConnection('marketing-posts')->onQueue('marketing-posts');}
 public function handle(MarketingPostQueue $service):void{$service->run($this->dispatchId);}
 public function failed(?\Throwable $error):void{app(MarketingPostQueue::class)->quarantine($this->dispatchId);}
}
