<?php
namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Evidence only: neither configured fees nor delivery state establish settlement. */
final class FinancialEvidence
{
    public function query(array $v,?\Illuminate\Database\Query\Builder $paidOrders=null,?string $asOf=null): array
    {
        $start=CarbonImmutable::parse($v['from'],'Asia/Kuala_Lumpur')->utc();
        $end=CarbonImmutable::parse($v['to'],'Asia/Kuala_Lumpur')->addDay()->utc();
        $cutoff=$asOf?CarbonImmutable::parse($asOf)->utc():CarbonImmutable::now('UTC');
        $q=DB::table('order_payments as p')->join('orders as o','o.id','=','p.order_id')
            ->where('p.proof_reviewed_at','<=',$cutoff)
            ->where('p.currency',$v['currency'])->where('p.proof_reviewed_at','>=',$start)->where('p.proof_reviewed_at','<',$end);
        if($v['search']??'')$q->whereRaw("o.number LIKE ? ESCAPE '!'",['%'.str_replace(['!','%','_'],['!!','!%','!_'],$v['search']).'%']);
        $cohort=($paidOrders??app(SalesReport::class)->firstPaidOrders($v))->select('o.id','o.tax_amount');
        $lines=DB::table('order_tax_lines')->select('order_id')->selectRaw('COUNT(*) as snapshot_line_count, SUM(tax_amount) as snapshot_tax_minor')->groupBy('order_id');
        $tax=DB::query()->fromSub($cohort,'paid')->leftJoinSub($lines,'tax','tax.order_id','=','paid.id');
        $unverified=(clone $tax)->where(fn($q)=>$q->whereNull('tax.snapshot_line_count')->orWhereColumn('tax.snapshot_tax_minor','<>','paid.tax_amount'))->count();
        $verified=(clone $tax)->whereNotNull('tax.snapshot_line_count')->whereColumn('tax.snapshot_tax_minor','paid.tax_amount');
        return [
            'tax_snapshot'=>['recorded_order_tax_minor'=>$unverified>0?null:(int)(clone $verified)->sum('tax.snapshot_tax_minor'),'verified_orders'=>(clone $verified)->count(),'unverified_orders'=>$unverified,
                'basis'=>'Immutable order tax lines reconciled to the original recorded order tax, once per first-dated-payment order in the selected period/currency/search. This is an order cohort snapshot, not cash tax collected, tax remitted, refund tax or a filing calculation. Missing or mismatched snapshots remain unavailable.'],
            'proof_reviews'=>$q->select('p.proof_status')->selectRaw('COUNT(*) as reviews')->groupBy('p.proof_status')->orderBy('p.proof_status')->get(),
            'reconciliation'=>['provider_settlement_minor'=>null,'actual_fees_minor'=>null,'cod_received_minor'=>null,'tax_remitted_minor'=>null,'refund_tax_minor'=>null,'net_profit_minor'=>null],
            'financial_basis'=>'Cashflow uses the same verified event dates and filters as Sales. Proof review counts use the recorded review date, not payment confirmation; they are current review states, not an audit-event count. CSV exports cashflow records only. Provider statements, actual fees, COD remittances, tax remittances and refund tax allocation are not connected. Configured rates and shipment delivery are never treated as settlement evidence.',
        ];
    }
}
