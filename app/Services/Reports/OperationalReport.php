<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Bounded read models. No identity inference, stock reconstruction or financial allocation. */
final class OperationalReport
{
    public const EXPORT_FIELDS=[
        'enquiries'=>['id','reference','enquiry_status','outcome','created_at','quotation_number','quotation_status','first_sent_at','accepted_at','order_number','order_currency'],
        'orders'=>['id','number','order_status','created_at','ordered_minor'],
        'products'=>['product_variant_id','sku','title','units','ordered_minor','orders'],
        'customers'=>['customer_id','orders','ordered_minor','first_order_in_period','last_order_in_period'],
        'shipping'=>['id','number','shipment_status','carrier_name','created_at','dispatched_at','delivered_at','shipping_minor'],
    ];
    public function rows(string $type, array $filters): Builder
    {
        $start=CarbonImmutable::parse($filters['from'], 'Asia/Kuala_Lumpur')->utc();
        $end=CarbonImmutable::parse($filters['to'], 'Asia/Kuala_Lumpur')->addDay()->utc();
        $orders=DB::table('orders')->where('currency', $filters['currency'])
            ->where('created_at', '>=', $start)->where('created_at', '<', $end);
        $search=$filters['search'] ?? '';
        // Bound parameters; LIKE metacharacters are literal on both supported databases.
        $search=str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        $q=match ($type) {
            'enquiries'=>DB::table('product_enquiries as e')
                ->leftJoin('enquiry_quotations as qt','qt.product_enquiry_id','=','e.id')
                ->leftJoinSub(DB::table('enquiry_quotation_versions')->where('sent_at','<=',now()->utc())->select('enquiry_quotation_id')->selectRaw('MIN(sent_at) as first_sent_at')->groupBy('enquiry_quotation_id'),'sent','sent.enquiry_quotation_id','=','qt.id')
                ->leftJoin('quotation_acceptances as a',function($join){$join->on('a.enquiry_quotation_id','=','qt.id')->where('a.accepted_at','<=',now()->utc());})
                ->leftJoin('orders as linked','linked.id','=','a.order_id')
                ->where('e.created_at','>=',$start)->where('e.created_at','<',$end)
                ->select('e.id','e.reference','e.status as enquiry_status','e.outcome','e.created_at','qt.number as quotation_number','qt.status as quotation_status','sent.first_sent_at','a.accepted_at','linked.id as order_id','linked.number as order_number','linked.currency as order_currency'),
            'orders'=>(clone $orders)->select('id', 'number', 'status as order_status', 'created_at', 'total as ordered_minor', 'customer_id'),
            'products'=>DB::table('order_items as i')->joinSub($orders, 'o', 'o.id', '=', 'i.order_id')
                ->select('i.product_variant_id', 'i.sku', 'i.title')
                ->selectRaw('SUM(i.quantity) as units, SUM(i.line_total) as ordered_minor, COUNT(DISTINCT i.order_id) as orders')
                ->groupBy('i.product_variant_id', 'i.sku', 'i.title'),
            'customers'=>DB::table('customers as c')->joinSub($orders, 'o', 'o.customer_id', '=', 'c.id')
                ->select('c.id as customer_id')->selectRaw('COUNT(o.id) as orders, SUM(o.total) as ordered_minor, MIN(o.created_at) as first_order_in_period, MAX(o.created_at) as last_order_in_period')
                ->groupBy('c.id'),
            'shipping'=>DB::table('shipments as s')->join('orders as o', 'o.id', '=', 's.order_id')
                ->where('s.currency', $filters['currency'])->where('s.created_at', '>=', $start)->where('s.created_at', '<', $end)
                ->select('s.id', 'o.number', 's.status as shipment_status', 's.carrier_name', 's.created_at', 's.dispatched_at', 's.delivered_at', 's.cancelled_at', 's.shipping_cost as shipping_minor'),
        };
        if ($search !== '') {
            $column=match ($type) {'enquiries'=>'e.reference','orders'=>'number', 'products'=>'i.sku', 'customers'=>'c.id', 'shipping'=>'o.number'};
            $q->whereRaw("$column LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }
        if($type==='orders'&&($filters['metric']??null)==='paid_orders')$q->whereIn('id',DB::table('order_payments')->where('currency',$filters['currency'])->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('amount')->whereNotNull('confirmed_at')->where('confirmed_at','<=',now()->utc())->select('order_id'));
        if($type==='enquiries'&&($filters['metric']??null)==='paid_quotes')$q->whereIn('a.order_id',DB::table('order_payments as paid')->join('orders as paid_order','paid_order.id','=','paid.order_id')->whereColumn('paid.currency','paid_order.currency')->whereIn('paid.status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('paid.amount')->whereNotNull('paid.confirmed_at')->where('paid.confirmed_at','<=',now()->utc())->select('paid.order_id'));
        if($type==='shipping'&&($filters['metric']??null)==='delivered_shipments')$q->whereNull('s.cancelled_at')->whereNotIn('s.status',['cancelled','booking_failed'])->whereNotNull('s.dispatched_at')->whereNotNull('s.delivered_at')->whereColumn('s.delivered_at','>=','s.dispatched_at')->where('s.dispatched_at','<=',now()->utc())->where('s.delivered_at','<=',now()->utc());
        return $q;
    }

    public function orderedRows(string $type, array $filters): Builder
    {
        $q=$this->rows($type, $filters);
        return match ($type) {
            'enquiries'=>$q->orderByDesc('e.created_at')->orderByDesc('e.id'),
            'orders'=>$q->orderByDesc('created_at')->orderByDesc('id'),
            'products'=>$q->orderByDesc('units')->orderBy('i.sku')->orderBy('i.product_variant_id')->orderBy('i.title'),
            'customers'=>$q->orderByDesc('orders')->orderBy('c.id'),
            'shipping'=>$q->orderByDesc('s.created_at')->orderByDesc('s.id'),
        };
    }

    public function query(string $type, array $filters): array
    {
        $limitations=[
            'enquiries'=>'Enquiries created in the period, with current enquiry/quotation status and subsequent recorded sent/accepted/order-link evidence up to as-of. Currency does not filter enquiries; linked orders show their own currency. Acceptance and order creation do not imply confirmed payment. Contact details and requirements are excluded.',
            'orders'=>'Orders created in the period, with current order status. Payment and shipment evidence is counted separately below. Enquiries use a separate cohort. Ordered value is not collections.',
            'products'=>'Item snapshots on orders created in the period, including pending/cancelled orders. Ordered units and recorded line totals are not paid sales. Promotion/refund allocation, margins, stock and First Count are not available.',
            'customers'=>'Canonical customer IDs only, on orders created in the period. Guest orders are excluded; no email/phone identity merging. Ordered value is not lifetime spend. RFM, new/returning cohorts and retention are not available.',
            'shipping'=>'Shipments created in the period, with current shipment status and recorded dispatch/delivery dates. History retains cancelled and failed attempts; progress excludes cancelled/failed bookings. Transit averages use only non-future date pairs with delivery on or after dispatch. Trend groups this shipment-created cohort by creation day and recorded progress to as-of, not event-date traffic. Manual/provider records share this recorded-date basis; provider SLA, completeness and COD settlement remain unverified. Currency filters shipment cost currency for parcels; the separate waiting queue uses order currency and creation date, processing status and no effective dispatch through as-of. Waiting age is from order creation, not payment confirmation or a provider SLA.',
        ];
        $coverage=['state'=>'partial', 'marketplaces'=>'Source not connected / not verified'];
        if ($type==='customers') {
            $start=CarbonImmutable::parse($filters['from'], 'Asia/Kuala_Lumpur')->utc();
            $end=CarbonImmutable::parse($filters['to'], 'Asia/Kuala_Lumpur')->addDay()->utc();
            $coverage['guest_orders']=DB::table('orders')->whereNull('customer_id')->where('currency',$filters['currency'])->where('created_at','>=',$start)->where('created_at','<',$end)->count();
        }
        $extra=$type==='orders' ? ['lifecycle'=>$this->lifecycle($filters)] : [];
        if($type==='shipping'){$extra=$this->shippingSummary($filters);$coverage+=$extra['coverage'];unset($extra['coverage']);}
        if ($type==='enquiries') {
            $cohort=DB::query()->fromSub($this->rows($type,$filters),'enquiry_cohort');
            $timing=$this->enquiryTiming($filters);
            $extra=['lifecycle'=>[
                'open_enquiries'=>(clone $cohort)->whereIn('enquiry_status',['new','following_up'])->count(),
                'average_conversion_hours'=>$timing['average_conversion_hours'],
                'conversion_sample_size'=>$timing['conversion_sample_size'],
                'conversion_invalid_time_pairs'=>$timing['conversion_invalid_time_pairs'],
                'enquiries_created'=>(clone $cohort)->count(),
                'enquiries_quoted'=>(clone $cohort)->whereNotNull('first_sent_at')->count(),
                'enquiries_accepted'=>(clone $cohort)->whereNotNull('accepted_at')->count(),
                'enquiries_linked_to_orders'=>(clone $cohort)->whereNotNull('order_number')->count(),
                'paid_quote_orders'=>DB::table('order_payments as paid')->join('orders as paid_order','paid_order.id','=','paid.order_id')
                    ->whereIn('paid.order_id',$this->rows($type,$filters)->select('a.order_id'))
                    ->whereColumn('paid.currency','paid_order.currency')->whereIn('paid.status',PaymentEvidence::CONFIRMED_STATUSES)
                    ->whereNotNull('paid.amount')->whereNotNull('paid.confirmed_at')->where('paid.confirmed_at','<=',now()->utc())->distinct()->count('paid.order_id'),
                'basis'=>$limitations[$type].' These are overlapping current cohort stages, not independent period event counts.',
            ]];
        }
        return $extra+['filters'=>$filters+['timezone'=>'Asia/Kuala_Lumpur'], 'as_of'=>now()->toIso8601String(),
            'basis'=>$limitations[$type], 'coverage'=>$coverage,
            'records'=>$this->orderedRows($type,$filters)->paginate(20,['*'],'page',$filters['page']??1)];
    }

    private function lifecycle(array $filters): array
    {
        $cohort=$this->rows('orders',$filters)->select('id');
        $asOf=now()->utc();
        $payments=DB::table('order_payments')->whereIn('order_id',$cohort)->where('currency',$filters['currency'])
            ->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('amount')->whereNotNull('confirmed_at')->where('confirmed_at','<=',$asOf);
        $refunds=DB::table('order_refunds')->whereIn('order_id',$cohort)->where('status','completed')->whereNotNull('completed_at')->where('completed_at','<=',$asOf);
        $shipments=DB::table('shipments')->whereIn('order_id',$cohort)->whereNull('cancelled_at')->whereNotIn('status',['cancelled','booking_failed']);
        $latest=DB::table('order_payments')->select('order_id')->selectRaw('MAX(id) as latest_id')->groupBy('order_id');
        $review=DB::table('order_payments as p')->joinSub($latest,'latest','latest.latest_id','=','p.id')->whereIn('p.order_id',$cohort)
            ->where('p.gateway','bank_transfer')->where('p.proof_status','uploaded')->whereNotNull('p.proof_path')->where('p.proof_path','<>','');
        return [
            'payment_review_orders'=>(clone $review)->distinct()->count('p.order_id'),
            'processing_without_dispatch'=>$this->processingQueue($filters)->count(),
            'orders_created'=>(clone $cohort)->count(),
            'orders_with_confirmed_payments'=>(clone $payments)->distinct()->count('order_id'),
            'orders_dispatched'=>(clone $shipments)->whereNotNull('dispatched_at')->where('dispatched_at','<=',$asOf)->distinct()->count('order_id'),
            'orders_delivered'=>(clone $shipments)->whereNotNull('delivered_at')->where('delivered_at','<=',$asOf)->distinct()->count('order_id'),
            'orders_with_completed_refunds'=>(clone $refunds)->distinct()->count('order_id'),
            'confirmed_minor_to_date'=>(int)(clone $payments)->sum('amount'),
            'refunded_minor_to_date'=>(int)(clone $refunds)->sum('amount'),
            'undated_confirmed_payments_excluded'=>DB::table('order_payments')->whereIn('order_id',$cohort)->where('currency',$filters['currency'])->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNull('confirmed_at')->count(),
            'undated_completed_refunds_excluded'=>DB::table('order_refunds')->whereIn('order_id',$cohort)->where('status','completed')->whereNull('completed_at')->count(),
            'basis'=>'Distinct orders created in the selected period, matching currency/search. Subsequent verified payments, refunds and non-cancelled shipment events are included up to as-of. Stages overlap and are not a strict conversion funnel. Partial payments do not mean fully paid; undated events are excluded. This differs from Sales event-date cashflow.',
        ];
    }

    public function attentionOrders(array $v,bool $allCurrent=false):array
    {
        $asOf=now()->utc();$cohort=$allCurrent?DB::table('orders')->where('currency',$v['currency']):$this->rows('orders',$v);
        $cohort->where('created_at','<=',$asOf);
        $latest=DB::table('order_payments')->select('order_id')->selectRaw('MAX(id) as latest_id')->groupBy('order_id');
        $review=DB::table('order_payments as p')->joinSub($latest,'latest','latest.latest_id','=','p.id')->whereIn('p.order_id',(clone $cohort)->select('id'))->where('p.gateway','bank_transfer')->where('p.proof_status','uploaded')->whereNotNull('p.proof_path')->where('p.proof_path','<>','');
        $queue=$this->processingQueue($v,$allCurrent);
        $paymentRows=(clone $review)->selectRaw('p.order_id as order_id, 1 as payment_review, 0 as awaiting_dispatch');
        $dispatchRows=(clone $queue)->selectRaw('id as order_id, 0 as payment_review, 1 as awaiting_dispatch');
        $attention=DB::query()->fromSub($paymentRows->unionAll($dispatchRows),'attention_rows')->select('order_id')->selectRaw('MAX(payment_review) as payment_review, MAX(awaiting_dispatch) as awaiting_dispatch')->groupBy('order_id');
        $records=DB::table('orders as o')->joinSub($attention,'a','a.order_id','=','o.id')->select('o.id','o.number','o.status','o.created_at','a.payment_review','a.awaiting_dispatch');
        $total=(clone $records)->count();
        return ['filters'=>$v+['timezone'=>'Asia/Kuala_Lumpur','all_current'=>$allCurrent],'as_of'=>$asOf->toIso8601String(),'facts'=>['payment_review_orders'=>(clone $review)->distinct()->count('p.order_id'),'processing_without_dispatch'=>(clone $queue)->count(),'attention_orders'=>$total],
            'records'=>$records->orderBy('o.created_at')->orderBy('o.id')->limit(200)->get()->map(fn($r)=>(array)$r)->all(),'possibly_truncated'=>$total>200,'coverage'=>['state'=>'recorded_attention_rules_only'],
            'basis'=>($allCurrent?'All current orders regardless of creation date. ':'Orders created in the selected period. ').'Latest uploaded bank proof needs review; processing orders without an effective dated dispatch need dispatch. These are recorded workflow states, not inferred customer urgency. No proof files or contact data returned.'];
    }

    private function processingQueue(array $v,bool $allCurrent=false): Builder
    {
        $asOf=now()->utc();
        $scope=$allCurrent?DB::table('orders')->where('currency',$v['currency']):$this->rows('orders',$v);
        return DB::table('orders')->whereIn('id',$scope->select('id'))->where('status','processing')->where('created_at','<=',$asOf)
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('shipments as s')->whereColumn('s.order_id','orders.id')->whereNull('s.cancelled_at')->whereNotIn('s.status',['cancelled','booking_failed'])->whereNotNull('s.dispatched_at')->where('s.dispatched_at','<=',$asOf));
    }

    private function enquiryTiming(array $v): array
    {
        $paid=DB::table('order_payments as p')->join('orders as o','o.id','=','p.order_id')->whereColumn('p.currency','o.currency')
            ->whereIn('p.status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('p.amount')->whereNotNull('p.confirmed_at')->where('p.confirmed_at','<=',now()->utc())
            ->select('p.order_id')->selectRaw('MIN(p.confirmed_at) as first_paid_at')->groupBy('p.order_id');
        $converted=DB::query()->fromSub($this->rows('enquiries',$v),'e')->joinSub($paid,'paid','paid.order_id','=','e.order_id');
        $invalid=(clone $converted)->whereColumn('first_paid_at','<','created_at')->count();
        $valid=(clone $converted)->whereColumn('first_paid_at','>=','created_at');
        $hours=DB::getDriverName()==='sqlite'?"(julianday(first_paid_at)-julianday(created_at))*24.0":"TIMESTAMPDIFF(SECOND, created_at, first_paid_at)/3600.0";
        $average=(clone $valid)->selectRaw("AVG($hours) as hours")->first()->hours;
        return ['average_conversion_hours'=>$average===null?null:round((float)$average,2),'conversion_sample_size'=>(clone $valid)->count(),'conversion_invalid_time_pairs'=>$invalid];
    }

    private function shippingSummary(array $v): array
    {
        $asOf=now()->utc();$history=DB::query()->fromSub($this->rows('shipping',$v),'shipment_scope');
        // Corrections cancel the source attempt; failed bookings are not effective parcels.
        // Keep all attempts in the table/export, while progress uses effective attempts only.
        $scope=(clone $history)->whereNull('cancelled_at')->whereNotIn('shipment_status',['cancelled','booking_failed']);
        $queue=$this->processingQueue($v);
        $age=DB::getDriverName()==='sqlite'?"(julianday(?)-julianday(created_at))*24.0":"TIMESTAMPDIFF(SECOND, created_at, ?)/3600.0";
        $queueAverage=(clone $queue)->selectRaw("AVG($age) as hours",[$asOf])->first()->hours;
        $valid=(clone $scope)->whereNotNull('dispatched_at')->whereNotNull('delivered_at')->whereColumn('delivered_at','>=','dispatched_at')->where('dispatched_at','<=',$asOf)->where('delivered_at','<=',$asOf);
        $hours=DB::getDriverName()==='sqlite'?"(julianday(delivered_at)-julianday(dispatched_at))*24.0":"TIMESTAMPDIFF(SECOND, dispatched_at, delivered_at)/3600.0";
        $average=(clone $valid)->selectRaw("AVG($hours) as hours")->first()->hours;
        $missingCost=(clone $scope)->whereNull('shipping_minor')->count();
        $coverage=[
            'inactive_attempts'=>(clone $history)->count()-(clone $scope)->count(),
            'deliveries_missing_dates'=>(clone $scope)->where('shipment_status','delivered')->where(fn($q)=>$q->whereNull('dispatched_at')->orWhereNull('delivered_at'))->count(),
            'invalid_time_pairs'=>(clone $scope)->whereNotNull('dispatched_at')->whereNotNull('delivered_at')->whereColumn('delivered_at','<','dispatched_at')->count(),
            'future_event_times'=>(clone $scope)->where(fn($q)=>$q->where('dispatched_at','>',$asOf)->orWhere('delivered_at','>',$asOf))->count(),
            'missing_shipping_costs'=>$missingCost,
        ];
        $day=DB::getDriverName()==='sqlite'?"date(created_at, '+8 hours')":"DATE(DATE_ADD(created_at, INTERVAL 8 HOUR))";
        $byDay=(clone $scope)->selectRaw("$day as day, SUM(CASE WHEN dispatched_at IS NOT NULL AND dispatched_at <= ? THEN 1 ELSE 0 END) as dispatched_count, SUM(CASE WHEN dispatched_at IS NOT NULL AND delivered_at IS NOT NULL AND delivered_at >= dispatched_at AND dispatched_at <= ? AND delivered_at <= ? THEN 1 ELSE 0 END) as delivered_count",[$asOf,$asOf,$asOf])->groupByRaw($day)->get()->keyBy('day');
        $trend=[];for($date=CarbonImmutable::parse($v['from']);$date->toDateString()<=$v['to'];$date=$date->addDay()){$key=$date->toDateString();$trend[]=$byDay->get($key)??['day'=>$key,'dispatched_count'=>0,'delivered_count'=>0];}
        return ['stats'=>['shipments_created'=>(clone $history)->count(),'effective_shipments'=>(clone $scope)->count(),'awaiting_dispatch_orders'=>(clone $queue)->count(),'average_awaiting_dispatch_hours'=>$queueAverage===null?null:round((float)$queueAverage,2),'recorded_deliveries'=>(clone $valid)->count(),'average_transit_hours'=>$average===null?null:round((float)$average,2),'recorded_shipping_minor'=>$missingCost>0?null:(int)(clone $scope)->sum('shipping_minor'),'cod_settlement_minor'=>null],
            'coverage'=>$coverage,'trend'=>$trend,'carriers'=>(clone $scope)->select('carrier_name')->selectRaw('COUNT(*) as shipments')->groupBy('carrier_name')->orderBy('carrier_name')->get()];
    }
}
