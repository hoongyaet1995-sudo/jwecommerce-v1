<?php
namespace App\Jobs;
use App\Services\Marketplace\ShopeeOAuthBatchProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessShopeeOAuthBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=3;
    public function __construct(public int $batchId, public bool $retry=false) { $this->onQueue('marketplace'); }
    public function handle(ShopeeOAuthBatchProcessor $processor): void { $processor->process($this->batchId,$this->retry); }
}
