<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PurchasingCustomersReport
{
    private ?array $eligibleIds=null;
    private function eligibleCustomers(array $v): array
    {
        if($this->eligibleIds!==null)return $this->eligibleIds;
        $candidates=DB::table('customers')->whereIn('id',DB::table('orders')->whereIn('id',$this->periodPayments($v)->select('p.order_id'))->select('customer_id'))
            ->select('id','email','phone')->limit(10001)->get();
        abort_if($candidates->count()>10000,422,'Select a narrower period: at most 10,000 candidate customers.');
        return $this->eligibleIds=$candidates->filter(fn($c)=>\App\Services\Customers\ContactValueNormalizer::email($c->email)!==null||\App\Services\Customers\ContactValueNormalizer::phone($c->phone)!==null)->pluck('id')->all();
    }
    public const EXPORT_FIELDS=['customer_id','paid_orders','confirmed_minor','refunded_minor_to_date','net_paid_minor','first_payment_in_period','last_payment_in_period','first_verified_payment','cohort'];

    private function periodPayments(array $v): Builder
    {
        $start=CarbonImmutable::parse($v['from'],'Asia/Kuala_Lumpur')->utc();
        $end=CarbonImmutable::parse($v['to'],'Asia/Kuala_Lumpur')->addDay()->utc();
        return DB::table('order_payments as p')->join('orders as pay_order','pay_order.id','=','p.order_id')
            ->where('p.currency',$v['currency'])->whereColumn('p.currency','pay_order.currency')
            ->whereIn('p.status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('p.amount')
            ->where('p.confirmed_at','>=',$start)->where('p.confirmed_at','<',$end)->where('p.confirmed_at','<=',now()->utc())
            ->select('p.order_id')->selectRaw('SUM(p.amount) as confirmed_minor, MIN(p.confirmed_at) as first_in_period, MAX(p.confirmed_at) as last_in_period')->groupBy('p.order_id');
    }

    public function rows(string $type,array $v): Builder
    {
        $start=CarbonImmutable::parse($v['from'],'Asia/Kuala_Lumpur')->utc();
        // A known earlier purchase proves returning. Otherwise incomplete history prevents a new-buyer claim.
        $history=DB::table('order_payments as h')->join('orders as history_order','history_order.id','=','h.order_id')
            ->whereNotNull('history_order.customer_id')->whereIn('h.status',PaymentEvidence::CONFIRMED_STATUSES)
            ->where(fn($q)=>$q->whereNull('h.confirmed_at')->orWhere('h.confirmed_at','<=',now()->utc()))
            ->select('history_order.customer_id')->selectRaw("MIN(CASE WHEN h.amount IS NOT NULL AND h.currency=history_order.currency THEN h.confirmed_at ELSE NULL END) as first_verified_payment, SUM(CASE WHEN h.confirmed_at IS NULL OR h.amount IS NULL OR h.currency IS NULL OR h.currency<>history_order.currency THEN 1 ELSE 0 END) as uncertain_history")
            ->groupBy('history_order.customer_id');
        $refunds=DB::table('order_refunds')->where('status','completed')->where(fn($q)=>$q->whereNull('completed_at')->orWhere('completed_at','<=',now()->utc()))
            ->select('order_id')->selectRaw('SUM(CASE WHEN completed_at IS NOT NULL THEN amount ELSE 0 END) as refunded_minor, SUM(CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END) as undated_refunds')->groupBy('order_id');
        $search=str_replace(['!','%','_'],['!!','!%','!_'],$v['search']??'');
        return DB::table('customers as c')->join('orders as o','o.customer_id','=','c.id')
            ->whereIn('c.id',$this->eligibleCustomers($v))
            ->joinSub($this->periodPayments($v),'period','period.order_id','=','o.id')
            ->joinSub($history,'history','history.customer_id','=','c.id')
            ->leftJoinSub($refunds,'refunds','refunds.order_id','=','o.id')
            ->when($search!=='',fn($q)=>$q->whereRaw("c.id LIKE ? ESCAPE '!'",['%'.$search.'%']))
            ->select('c.id as customer_id','history.first_verified_payment')
            ->selectRaw("COUNT(o.id) as paid_orders, SUM(period.confirmed_minor) as confirmed_minor, SUM(COALESCE(refunds.undated_refunds,0)) as undated_completed_refunds, CASE WHEN SUM(COALESCE(refunds.undated_refunds,0))>0 THEN NULL ELSE SUM(COALESCE(refunds.refunded_minor,0)) END as refunded_minor_to_date, CASE WHEN SUM(COALESCE(refunds.undated_refunds,0))>0 THEN NULL ELSE SUM(period.confirmed_minor)-SUM(COALESCE(refunds.refunded_minor,0)) END as net_paid_minor, MIN(period.first_in_period) as first_payment_in_period, MAX(period.last_in_period) as last_payment_in_period, CASE WHEN history.first_verified_payment < ? THEN 'returning' WHEN history.uncertain_history > 0 THEN 'unknown' ELSE 'new' END as cohort",[$start])
            ->groupBy('c.id','history.first_verified_payment','history.uncertain_history')
            ->when(($v['metric']??null)==='new_buyers',fn($q)=>$q->having('cohort','new'))
            ->when(($v['metric']??null)==='returning_buyers',fn($q)=>$q->having('cohort','returning'));
    }

    public function orderedRows(string $type,array $v): Builder
    {
        return $this->rows($type,$v)->orderByDesc('net_paid_minor')->orderByDesc('paid_orders')->orderBy('c.id');
    }

    public function query(string $type,array $v): array
    {
        $rows=$this->rows($type,$v);
        $cohort=DB::query()->fromSub($rows,'buyers');
        $stats=(clone $cohort)->selectRaw("COUNT(*) as purchasing_customers, COALESCE(SUM(CASE WHEN cohort='new' THEN 1 ELSE 0 END),0) as new_customers, COALESCE(SUM(CASE WHEN cohort='returning' THEN 1 ELSE 0 END),0) as returning_customers, COALESCE(SUM(CASE WHEN cohort='unknown' THEN 1 ELSE 0 END),0) as unclassified_customers, COALESCE(SUM(paid_orders),0) as paid_orders")->first();
        $stats=(array)$stats;foreach($stats as &$n)$n=(int)$n;unset($n);
        $stats['average_orders_per_buyer']=$stats['purchasing_customers']>0?round($stats['paid_orders']/$stats['purchasing_customers'],2):null;
        $day=DB::getDriverName()==='sqlite'?"date(first_payment_in_period, '+8 hours')":"DATE(DATE_ADD(first_payment_in_period, INTERVAL 8 HOUR))";
        $activity=(clone $cohort)->selectRaw("$day as day, SUM(CASE WHEN cohort='new' THEN 1 ELSE 0 END) as new_customers, SUM(CASE WHEN cohort='returning' THEN 1 ELSE 0 END) as returning_customers")->groupByRaw($day)->get()->keyBy('day');
        $trend=[];for($date=CarbonImmutable::parse($v['from']);$date->toDateString()<=$v['to'];$date=$date->addDay()){
            $key=$date->toDateString();$trend[]=$activity->get($key)??['day'=>$key,'new_customers'=>0,'returning_customers'=>0];
        }
        return ['filters'=>$v+['timezone'=>'Asia/Kuala_Lumpur'],'as_of'=>now()->toIso8601String(),'stats'=>$stats,'trend'=>$trend,
            'records'=>$this->orderedRows($type,$v)->paginate(20,['*'],'page',$v['page']??1),
            'coverage'=>['state'=>'partial','marketplaces'=>'Source not connected / not verified',
                'undated_completed_refunds'=>(int)(clone $cohort)->sum('undated_completed_refunds'),
                'guest_orders'=>DB::query()->fromSub($this->periodPayments($v)->whereNull('pay_order.customer_id'),'guest')->count(),
                'undated_confirmed_payments'=>DB::table('order_payments')->where('currency',$v['currency'])->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNull('confirmed_at')->count()],
            'basis'=>'Canonical customer IDs with dated confirmed payments in the selected period/currency; guest orders and canonical records without a valid email or international phone are excluded, with no email/phone merging. New means no earlier verified payment in any currency and no uncertain history; known prior payments prove returning, otherwise incomplete history remains unknown. Net paid value is selected-period payments less completed refunds to date on those orders, not Sales event-date cashflow or lifetime value. Unknown completion dates make the affected net/refunded values unavailable. Rows rank by selected-period net paid, with unknown values last. Trend assigns each classified buyer once to their first purchasing day in this period; unknown buyers are excluded from the two-series trend. RFM is not available.'];
    }
}
