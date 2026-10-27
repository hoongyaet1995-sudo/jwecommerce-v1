<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class MarketingTemplateJob implements ShouldQueue {
 use Queueable;public int $tries=1;public int $timeout=2400;
 public function __construct(public string $runId,public string $action){}
 public function handle(\App\Services\Marketing\MarketingTemplateWorkflow $workflow):void{$workflow->execute($this->runId,$this->action);}
 public function failed(?\Throwable $e):void{if($r=\App\Models\MarketingTemplateRun::find($this->runId))app(\App\Services\Marketing\MarketingTemplateWorkflow::class)->pause($r,$e??new \RuntimeException('worker_failed'));}
}
