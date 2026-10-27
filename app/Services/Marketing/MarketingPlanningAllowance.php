<?php
namespace App\Services\Marketing;
use App\Models\{MarketingAiRun,MarketingGenerationQuote};

/** Service-included planning remains bounded independently of paid media consent. */
class MarketingPlanningAllowance
{
    public function assertAvailable(string $project,int $next=20000):void
    {
        if(!config('marketing.billing.enabled'))return;
        app(MarketingGenerationPricing::class)->configuration();
        // Trusted local configuration exempts only the approved project daily allowance.
        if(in_array($project,config('marketing.billing.included_daily_unlimited_projects',[]),true))return;
        $used=(int)round(MarketingAiRun::where('project_id',$project)->where('created_at','>=',now()->subDay())->sum('reserved_usd')*1000000);
        $used+=(int)MarketingGenerationQuote::where('project_id',$project)->where('included',true)->whereNotNull('confirmed_at')->where('confirmed_at','>=',now()->subDay())->sum('cap_microusd');
        abort_unless($used+$next<=config('marketing.billing.included_daily_microusd',500000),429,'本项目今日策划服务额度已用完；已保存内容保留，请稍后继续。');
    }
}
