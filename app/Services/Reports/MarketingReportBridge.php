<?php
namespace App\Services\Reports;

use App\Services\Marketing\MarketingAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Same owner-private analytics producer; no campaign, audience or provider mutation. */
final class MarketingReportBridge
{
    public const EXPORT_FIELDS=['campaign_id','name','sessions','page_views','item_views','add_to_cart','checkouts','quote_requests','quotations_sent','quotations_accepted','paid_orders','attributed_net_minor'];

    public function query(Request $request,array $v): array
    {
        $sourceRequest=Request::create('/admin/marketing/analytics/data','GET',['from'=>$v['from'],'to'=>$v['to'],'timezone'=>'Asia/Kuala_Lumpur','search'=>$v['search']??'']);
        $sourceRequest->setUserResolver(fn()=>$request->user());
        $source=app(MarketingAnalytics::class)->report($sourceRequest);
        $ids=array_column($source['campaigns'],'id');
        $start=CarbonImmutable::parse($v['from'],'Asia/Kuala_Lumpur')->utc();$end=CarbonImmutable::parse($v['to'],'Asia/Kuala_Lumpur')->addDay()->utc();
        $bad=DB::table('marketing_order_attributions')->whereIn('campaign_id',$ids)->where('state','paid')->where('paid_at','>=',$start)->where('paid_at','<',$end)
            ->where(fn($q)=>$q->whereNull('base_minor')->orWhereNull('base_currency')->orWhereColumn('refunded_minor','>','base_minor'))->pluck('campaign_id');
        $money=collect($source['money'])->where('base_currency',$v['currency'])->keyBy('campaign_id');
        // Match the source's all-history unconfirmed coverage: its date/currency are not trustworthy.
        $uncertain=DB::table('marketing_order_attributions')->whereIn('campaign_id',$ids)->whereIn('state',['evidence_unconfirmed','money_unconfirmed'])->pluck('campaign_id');
        $rows=[];
        foreach($source['campaigns'] as $c){$m=$money->get($c['id']);$rows[]=[
            'campaign_id'=>$c['id'],'name'=>$c['name'],'sessions'=>(int)$c['website_sessions'],'page_views'=>(int)$c['page_view'],'item_views'=>(int)$c['view_item'],'add_to_cart'=>(int)$c['add_to_cart'],
            'checkouts'=>(int)$c['begin_checkout'],'quote_requests'=>(int)$c['request_quote'],'quotations_sent'=>(int)$c['quotation_sent'],'quotations_accepted'=>(int)$c['quotation_accepted'],
            'paid_orders'=>(int)($m->paid_orders??0),'attributed_net_minor'=>$bad->contains($c['id'])||$uncertain->contains($c['id'])?null:($m?(int)$m->paid_minor-(int)$m->refunded_minor:0),
        ];}
        usort($rows,fn($a,$b)=>strcasecmp($a['name'],$b['name'])?:strcmp($a['campaign_id'],$b['campaign_id']));
        $totals=collect($source['currency_totals'])->firstWhere('currency',$v['currency']);
        $incomplete=(int)$source['unconfirmed']+$bad->count()>0;
        $trend=array_map(fn($d)=>['day'=>$d['date'],'sessions'=>(int)$d['sessions'],'checkouts'=>(int)$d['checkouts']],$source['trend']);
        $page=(int)($v['page']??1);
        return ['filters'=>$v+['timezone'=>'Asia/Kuala_Lumpur'],'as_of'=>$source['as_of'],'export_rows'=>$rows,
            'records'=>new LengthAwarePaginator(array_slice($rows,($page-1)*20,20),count($rows),20,$page,['path'=>'/admin/api/reports/marketing']),
            'stats'=>['sessions'=>(int)$source['summary']['website_sessions'],'page_views'=>(int)$source['summary']['page_view'],'checkouts'=>(int)$source['summary']['begin_checkout'],'attributed_paid_orders'=>array_sum(array_column($rows,'paid_orders')),'attributed_net_minor'=>$incomplete?null:(int)($totals['net_minor']??0),'roas'=>null],
            'trend'=>$trend,'coverage'=>['state'=>'partial','marketplaces'=>'Shopee / Lazada / TikTok Shop not connected as marketplace report sources','collection'=>$source['collection'],'unconfirmed_attributions'=>(int)$source['unconfirmed'],'inconsistent_paid_rows'=>$bad->count(),'retention'=>$source['retention']],
            'basis'=>'Existing Marketing Analytics, restricted to your own campaigns and merchant. '.' Activity counts include tracked website events across currencies; attribution money and paid-order counts use the selected saved base currency. Confirmed refunds to date apply to the attributed paid-order cohort; this differs from Sales event-date cashflow. Unknown attribution evidence remains unavailable, not zero. Visitor retention, consent and disabled collection limit completeness. Social TikTok campaigns are not TikTok Shop marketplace sales. Ad spend, ROAS and all-store conversion rates are unavailable. Search applies to campaign names across table, export and aggregates. Open Marketing Analytics for source-level details.'];
    }
}
