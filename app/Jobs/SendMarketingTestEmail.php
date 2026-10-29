<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SendMarketingTestEmail implements ShouldQueue {
 use Queueable;
 public int $tries=1;public int $timeout=120;public bool $failOnTimeout=true;
 public function __construct(public string $deliveryId){$this->onConnection('marketing-email')->onQueue('marketing-email');}
 public function handle(\App\Services\Marketing\MarketingEmailDelivery $s):void{$s->run($this->deliveryId);}
 public function failed(?\Throwable $e):void{app(\App\Services\Marketing\MarketingEmailDelivery::class)->unknown($this->deliveryId);}
}
