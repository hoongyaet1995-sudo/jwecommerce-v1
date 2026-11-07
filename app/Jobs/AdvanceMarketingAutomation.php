<?php
namespace App\Jobs;
use Illuminate\Foundation\Bus\Dispatchable;
/** No per-installation queue worker is required: response is sent before this bounded step. */
class AdvanceMarketingAutomation {
 use Dispatchable;
 public function handle():void {@set_time_limit(300);ignore_user_abort(true);app(\App\Services\Marketing\MarketingAutomationTrigger::class)->advanceOldest();}
}
