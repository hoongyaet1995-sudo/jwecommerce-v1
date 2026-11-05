<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Safe to rerun after recovery: never edits dates, transactions, identities or stock. */
final class DiagnoseReports extends Command
{
    protected $signature='reports:diagnose {--explain : Include read-only query plans for the default reporting period}';
    protected $description='Read-only Reports schema and trustworthy event-date coverage diagnostics';

    public function handle(): int
    {
        $started=hrtime(true);
        $required=[
            'orders'=>['id','number','customer_id','currency','created_at','status','total','subtotal','discount_amount','shipping_amount','payment_surcharge_amount','tax_amount','promotion_snapshot'],
            'order_payments'=>['id','order_id','gateway','amount','currency','status','confirmed_at','proof_status','proof_reviewed_at','proof_path'],
            'order_refunds'=>['id','order_id','amount','status','completed_at'],
            'order_tax_lines'=>['order_id','tax_amount','configuration_snapshot'],
            'order_items'=>['id','order_id','product_variant_id','sku','title','unit_price','quantity','line_total'],
            'customers'=>['id','email','phone'],
            'shipments'=>['id','order_id','carrier_name','currency','status','created_at','dispatched_at','delivered_at','cancelled_at','shipping_cost'],
            'product_enquiries'=>['id','reference','status','outcome','created_at'],
            'enquiry_quotations'=>['id','product_enquiry_id','number','status'],
            'enquiry_quotation_versions'=>['enquiry_quotation_id','sent_at'],
            'quotation_acceptances'=>['enquiry_quotation_id','accepted_at','order_id'],
            'return_requests'=>['order_id','order_item_id','resolved_refund_id','status','resolution','quantity','resolved_at'],
            'inventory_configuration'=>['id','operating_mode'],
            'inventory_pools'=>['id','pool_key'],
            'inventory_balances'=>['id','inventory_pool_id','product_variant_id','current_stock','counted_at','updated_at'],
        ];
        $missing=[];
        foreach ($required as $table=>$columns) {
            if (!Schema::hasTable($table)) { $missing[$table]=$columns; continue; }
            $absent=array_values(array_diff($columns,Schema::getColumnListing($table)));
            if ($absent) $missing[$table]=$absent;
        }
        $result=['read_only'=>true,'as_of'=>now()->toIso8601String(),'schema_missing'=>$missing,
            'recovery'=>'Restore the accepted code and dependencies, check migration status, then rerun this command. Never infer confirmed_at/completed_at from updated_at. Recover historical dates only from independently verified original events; no automatic backfill is performed.',
            'limitations'=>['marketplace reconciliation not verified','inventory uses canonical website availability with explicit pre-count verification limits; historical costs and net profit unavailable']];
        if (!$missing) {
            $undated=DB::table('order_payments')->whereIn('status',\App\Services\Reports\PaymentEvidence::CONFIRMED_STATUSES)->whereNull('confirmed_at');
            $groups=(clone $undated)->select('currency')->selectRaw('COUNT(*) as count, SUM(CASE WHEN amount IS NULL THEN 1 ELSE 0 END) as missing_amount_count')->groupBy('currency')->get();
            $safe=[];foreach($groups as $row){$currency=is_string($row->currency)&&preg_match('/^[A-Z]{3}$/',$row->currency)?$row->currency:'missing_or_invalid';$safe[$currency]??=['currency'=>$currency,'count'=>0,'missing_amount_count'=>0];$safe[$currency]['count']+=(int)$row->count;$safe[$currency]['missing_amount_count']+=(int)$row->missing_amount_count;}
            $result['undated_payment_scope']=['population'=>'All recorded confirmed-status payments across every currency, including missing currency; no date or search filter. Counts are payment rows, not distinct orders.','by_currency'=>array_values($safe),'by_status'=>(clone $undated)->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->get(),'cause_limit'=>'confirmed_at is absent. This does not establish the original producer defect or a recoverable confirmation date; original independent event evidence is required. No source records changed.'];
            $result['coverage']=[
                'undated_confirmed_payments'=>DB::table('order_payments')->whereIn('status',\App\Services\Reports\PaymentEvidence::CONFIRMED_STATUSES)->whereNull('confirmed_at')->count(),
                'undated_completed_refunds'=>DB::table('order_refunds')->where('status','completed')->whereNull('completed_at')->count(),
                'incomplete_confirmed_payment_money'=>DB::table('order_payments')->whereIn('status',\App\Services\Reports\PaymentEvidence::CONFIRMED_STATUSES)->where(fn($q)=>$q->whereNull('amount')->orWhereNull('currency'))->count(),
                'orders_without_promotion_snapshot'=>DB::table('orders')->whereNull('promotion_snapshot')->count(),
                'completed_refunds_without_item_links'=>DB::table('order_refunds as r')->where('r.status','completed')->whereNotExists(fn($q)=>$q->selectRaw('1')->from('return_requests as rr')->whereColumn('rr.resolved_refund_id','r.id')->where('rr.status','resolved_refund'))->count(),
                'guest_orders'=>DB::table('orders')->whereNull('customer_id')->count(),
                'delivered_without_verified_dates'=>DB::table('shipments')->where('status','delivered')->where(fn($q)=>$q->whereNull('dispatched_at')->orWhereNull('delivered_at'))->count(),
            ];
            if($this->option('explain')){
                $filters=['from'=>now('Asia/Kuala_Lumpur')->subDays(29)->toDateString(),'to'=>now('Asia/Kuala_Lumpur')->toDateString(),'currency'=>'MYR','search'=>''];
                $operational=app(\App\Services\Reports\OperationalReport::class);
                $queries=['sales_cashflow'=>app(\App\Services\Reports\SalesReport::class)->events($filters),'order_cohort'=>$operational->rows('orders',$filters),'shipment_cohort'=>$operational->rows('shipping',$filters)];
                $prefix=DB::getDriverName()==='sqlite'?'EXPLAIN QUERY PLAN ':'EXPLAIN ';
                foreach($queries as $name=>$query)$result['query_plans'][$name]=DB::select($prefix.$query->toSql(),$query->getBindings());
                $result['query_plan_scope']=$filters+['timezone'=>'Asia/Kuala_Lumpur','limitation'=>'Plans describe current local data, not a production-volume benchmark.'];
            }
        }
        $result['elapsed_ms']=round((hrtime(true)-$started)/1e6,2);
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
