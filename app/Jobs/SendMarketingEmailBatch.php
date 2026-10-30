<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SendMarketingEmailBatch implements ShouldQueue {use Queueable;public int $tries=1;public int $timeout=120;public bool $failOnTimeout=true;public function __construct(public string $batchId){$this->onConnection('marketing-email')->onQueue('marketing-email');}public function handle(\App\Services\Marketing\MarketingEmailBatches $s):void{$s->run($this->batchId);}public function failed(?\Throwable $e):void{app(\App\Services\Marketing\MarketingEmailBatches::class)->pause($this->batchId);}}
