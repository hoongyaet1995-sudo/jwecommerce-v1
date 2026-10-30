<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ProcessMarketingAudience implements ShouldQueue {
 use Queueable;
 public int $tries=1;public int $timeout=120;
 public function __construct(public string $taskId){}
 public function handle():void{app(\App\Services\Marketing\Audience\AudienceTasks::class)->step($this->taskId);}
 public function failed(?\Throwable $e):void{\Illuminate\Support\Facades\DB::table('marketing_audience_tasks')->where('id',$this->taskId)->where('state','queued')->update(['state'=>'failed','error_code'=>'worker_interrupted','updated_at'=>now()]);}
}
