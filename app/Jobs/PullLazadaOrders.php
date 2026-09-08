<?php
namespace App\Jobs;
use App\Services\Marketplace\LazadaOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;
class PullLazadaOrders implements ShouldQueue
{
 use Queueable;
 public int $tries=1;
 public int $timeout=120;
 public bool $failOnTimeout=true;
 public function __construct(public int $runId,public int $expectedPage=0){$this->onConnection('marketplace')->onQueue('marketplace');}
 public function handle(LazadaOrderService $orders):void
 {
  if(!DB::table('marketplace_order_runs')->where('id',$this->runId)->where('status','queued')->where('pages_completed',$this->expectedPage)
    ->update(['status'=>'running','updated_at'=>now()]))return;
  if(config('marketplace.operations_enabled')!==true&&config('marketplace.read_operations_enabled')!==true){$this->finish('marketplace_operations_disabled');return;}
  try{
   $run=DB::table('marketplace_order_runs')->find($this->runId);
   $page=$orders->pullPage($run->store_id,$run->time_from,$run->time_to,(int)($run->next_cursor?:0));
   DB::transaction(function()use($run,$page){
    $continue=$page['has_more'];
    if($continue&&$page['next_offset']>5000){DB::table('marketplace_order_runs')->where('id',$run->id)->update(['status'=>'failed','error_code'=>'lazada_offset_limit_reached','finished_at'=>now(),'updated_at'=>now()]);return;}
    DB::table('marketplace_order_runs')->where('id',$run->id)->update(['status'=>$continue?'queued':'succeeded','next_cursor'=>$continue?(string)$page['next_offset']:null,
      'pages_completed'=>$run->pages_completed+1,'orders_processed'=>$run->orders_processed+$page['orders'],'finished_at'=>$continue?null:now(),'updated_at'=>now()]);
    if($continue){$queue=config('queue.connections.marketplace');if(($queue['driver']??null)!=='database'||($queue['connection']??config('database.default'))!==DB::connection()->getName())throw new \RuntimeException('Order continuation requires the application database queue.');self::dispatch($run->id,$run->pages_completed+1)->beforeCommit();}
   });
  }catch(Throwable){$this->finish('lazada_order_page_failed');}
 }
 private function finish(string $code):void {DB::table('marketplace_order_runs')->where('id',$this->runId)->whereIn('status',['queued','running'])->where('pages_completed',$this->expectedPage)->update(['status'=>'failed','error_code'=>$code,'finished_at'=>now(),'updated_at'=>now()]);}
 public function failed(?Throwable $exception=null):void {$this->finish('lazada_order_page_failed');}
}
