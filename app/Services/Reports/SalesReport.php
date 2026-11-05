<?php
namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only cashflow; amounts remain integer transaction minor units. */
final class SalesReport
{
 private ?CarbonImmutable $cutoff=null;
 private function asOf(): CarbonImmutable { return $this->cutoff??=CarbonImmutable::now('UTC'); }
 private function totals(Builder $q): array {
  $t=(clone $q)->selectRaw("COALESCE(SUM(CASE WHEN kind='payment' THEN amount ELSE 0 END),0) as confirmed_minor, COALESCE(SUM(CASE WHEN kind='refund' THEN amount ELSE 0 END),0) as refunded_minor")->first();
  return ['confirmed_minor'=>(int)$t->confirmed_minor,'refunded_minor'=>(int)$t->refunded_minor,'net_minor'=>(int)$t->confirmed_minor-(int)$t->refunded_minor];
 }
 public function cutoff(): CarbonImmutable { return $this->asOf(); }
 public function firstPaidOrders(array $v): Builder {
  $first=DB::table('order_payments')->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('amount')->whereNotNull('confirmed_at')->where('confirmed_at','<=',$this->asOf())->where('currency',$v['currency'])->select('order_id')->selectRaw('MIN(confirmed_at) as first_confirmed_at')->groupBy('order_id');
  $q=DB::table('orders as o')->joinSub($first,'first','first.order_id','=','o.id')->where('o.currency',$v['currency'])
   ->where('first.first_confirmed_at','>=',CarbonImmutable::parse($v['from'],'Asia/Kuala_Lumpur')->utc())->where('first.first_confirmed_at','<',CarbonImmutable::parse($v['to'],'Asia/Kuala_Lumpur')->addDay()->utc());
  if($v['search']??'')$q->whereRaw("o.number LIKE ? ESCAPE '!'",['%'.str_replace(['!','%','_'],['!!','!%','!_'],$v['search']).'%']);
  return $q;
 }
 public function events(array $v): Builder {
  $start=CarbonImmutable::parse($v['from'], 'Asia/Kuala_Lumpur')->utc();
  $end=CarbonImmutable::parse($v['to'], 'Asia/Kuala_Lumpur')->addDay()->utc();
  $p=DB::table('order_payments as p')->join('orders as o','o.id','=','p.order_id')
   ->whereIn('p.status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('p.amount')->where('p.currency',$v['currency'])
   ->where('p.confirmed_at','<=',$this->asOf())->where('p.confirmed_at','>=',$start)->where('p.confirmed_at','<',$end)
   ->selectRaw("p.id as event_id, o.id as order_id, o.number, 'payment' as kind, p.confirmed_at as occurred_at, p.amount, p.gateway as method");
  $r=DB::table('order_refunds as r')->join('orders as o','o.id','=','r.order_id')
   ->where('r.status','completed')->where('o.currency',$v['currency'])
   ->where('r.completed_at','<=',$this->asOf())->where('r.completed_at','>=',$start)->where('r.completed_at','<',$end)
   ->selectRaw("r.id as event_id, o.id as order_id, o.number, 'refund' as kind, r.completed_at as occurred_at, r.amount, 'Refund' as method");
  return DB::query()->fromSub($p->unionAll($r),'events')->when($v['search']??null,fn($b,$s)=>$b->whereRaw("number LIKE ? ESCAPE '!'",['%'.str_replace(['!','%','_'],['!!','!%','!_'],$s).'%']));
 }
 public function query(array $v): array {
  $q=$this->events($v);
  $cohort=$this->firstPaidOrders($v);$paidOrders=(clone $cohort)->count();
  $days=(int)CarbonImmutable::parse($v['from'])->diffInDays(CarbonImmutable::parse($v['to']))+1;
  $previous=$v;$previous['to']=CarbonImmutable::parse($v['from'])->subDay()->toDateString();$previous['from']=CarbonImmutable::parse($v['from'])->subDays($days)->toDateString();
  $prior=$this->totals($this->events($previous));$current=$this->totals($q);
  $change=fn($key)=>['state'=>$prior[$key]===0?($current[$key]===0?'no_activity':'new_activity'):'comparable','percent'=>$prior[$key]===0?null:round(($current[$key]-$prior[$key])/abs($prior[$key])*100,2)];
  $totals=(clone $q)->selectRaw("COALESCE(SUM(CASE WHEN kind='payment' THEN amount ELSE 0 END),0) as confirmed_minor, COALESCE(SUM(CASE WHEN kind='refund' THEN amount ELSE 0 END),0) as refunded_minor")->first();
  $paid=(int)$totals->confirmed_minor;$refund=(int)$totals->refunded_minor;
  $day=DB::getDriverName()==='sqlite' ? "date(occurred_at, '+8 hours')" : "DATE(DATE_ADD(occurred_at, INTERVAL 8 HOUR))";
  $daily=(clone $q)->selectRaw("$day as day, SUM(CASE WHEN kind='payment' THEN amount ELSE 0 END) as confirmed_minor, SUM(CASE WHEN kind='refund' THEN amount ELSE 0 END) as refunded_minor")->groupByRaw($day)->orderBy('day')->get();
  $byDay=$daily->keyBy('day');
  $daily=collect();
  for($date=CarbonImmutable::parse($v['from']);$date->toDateString()<=$v['to'];$date=$date->addDay()) {
   $key=$date->toDateString();
   $daily->push($byDay->get($key) ?? (object)['day'=>$key,'confirmed_minor'=>0,'refunded_minor'=>0]);
  }
  $records=(clone $q)->orderByDesc('occurred_at')->orderBy('kind')->orderByDesc('event_id')->paginate(20,['*'],'page',$v['page']??1);
  return ['filters'=>$v+['timezone'=>'Asia/Kuala_Lumpur'],'as_of'=>$this->asOf()->toIso8601String(),'totals'=>['confirmed_minor'=>$paid,'refunded_minor'=>$refund,'net_minor'=>$paid-$refund,'paid_orders'=>$paidOrders,'average_order_minor'=>$paidOrders===0?null:(int)round((clone $cohort)->avg('o.total'))],
   'comparison'=>['from'=>$previous['from'],'to'=>$previous['to'],'previous'=>$prior,'confirmed_change'=>$change('confirmed_minor'),'net_change'=>$change('net_minor')], 'trend'=>$daily,
   'methods'=>(clone $q)->where('kind','payment')->select('method')->selectRaw('SUM(amount) as amount')->groupBy('method')->get(), 'records'=>$records,
   'coverage'=>['incomplete_payment_money_all_currencies'=>DB::table('order_payments')->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->where(fn($q)=>$q->whereNull('amount')->orWhereNull('currency'))->count(),'undated_scope'=>'All recorded history in '.$v['currency'].'; independent of the selected dates and search. Undated events cannot be assigned to a reporting period.','undated_payments'=>DB::table('order_payments')->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->where('currency',$v['currency'])->whereNull('confirmed_at')->count(),'undated_refunds'=>DB::table('order_refunds as r')->join('orders as o','o.id','=','r.order_id')->where('r.status','completed')->where('o.currency',$v['currency'])->whereNull('r.completed_at')->count()],
   'basis'=>'Confirmed payment and completed refund dates. Direct store only. Paid orders use first dated confirmed payment; AOV uses original order total including tax/shipping, independently from refunds. Comparison uses the preceding equal-length period with the same search/currency. Undated historical records are excluded and can make first-payment history incomplete.'];
 }
}
