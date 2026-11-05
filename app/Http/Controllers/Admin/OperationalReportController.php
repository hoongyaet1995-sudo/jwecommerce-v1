<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\OperationalReport;
use App\Services\Reports\PurchasingCustomersReport;
use App\Services\Reports\MerchandiseReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class OperationalReportController extends Controller
{
    public function __invoke(Request $request, string $type, OperationalReport $report)
    {
        $user=$request->user();
        $permissionType=match($type){'enquiries'=>'orders','customer-cohorts'=>'customers','merchandise'=>'products',default=>$type};
        abort_unless($user->is_super_admin || ($user->is_admin && $user->permissions()->where('permission',"reports.$permissionType.view")->exists()),403);
        $v=$request->validate(['from'=>'sometimes|date_format:Y-m-d', 'to'=>'sometimes|date_format:Y-m-d',
            'currency'=>'sometimes|string|size:3|regex:/^[A-Z]{3}$/', 'page'=>'sometimes|integer|min:1', 'search'=>'nullable|string|max:100', 'export'=>'sometimes|in:csv','metric'=>'nullable|in:paid_orders,paid_quotes,new_buyers,returning_buyers,delivered_shipments']);
        $allowedMetrics=match($type){'orders'=>['paid_orders'],'enquiries'=>['paid_quotes'],'customer-cohorts'=>['new_buyers','returning_buyers'],'shipping'=>['delivered_shipments'],default=>[]};
        abort_if(!empty($v['metric'])&&!in_array($v['metric'],$allowedMetrics,true),422,'This metric does not apply to this report.');
        $v+=['from'=>now('Asia/Kuala_Lumpur')->subDays(29)->toDateString(), 'to'=>now('Asia/Kuala_Lumpur')->toDateString(), 'currency'=>'MYR'];
        $days=CarbonImmutable::parse($v['from'])->diffInDays(CarbonImmutable::parse($v['to']),false);
        abort_if($days<0 || $days>366,422,'Select a period of up to 367 days.');
        if($type==='customer-cohorts')$report=app(PurchasingCustomersReport::class);
        if($type==='merchandise')$report=app(MerchandiseReport::class);
        if (($v['export']??null)==='csv') {
            $fileCurrency=$type==='enquiries'?'all-currencies':$v['currency'];
            // Bounded reports must reject before a streamed 200 response or CSV header exists.
            $boundedRows=$type==='merchandise'?$report->orderedRows($type,$v):null;
            $customerRows=$type==='customer-cohorts'?$report->orderedRows($type,$v):null;
            if($type!=='merchandise')(clone ($customerRows??$report->orderedRows($type,$v)))->limit(1)->get();
            return response()->streamDownload(function () use ($type,$v,$report,$boundedRows,$customerRows) {
                $out=fopen('php://output','w');
                $fields=match($type){'customer-cohorts'=>PurchasingCustomersReport::EXPORT_FIELDS,'merchandise'=>MerchandiseReport::EXPORT_FIELDS,default=>OperationalReport::EXPORT_FIELDS[$type]}; fputcsv($out,$fields);
                $rows=$boundedRows??($customerRows??$report->orderedRows($type,$v))->cursor();
                foreach ($rows as $row) {
                    $values=array_map(fn($field)=>$row->$field,$fields);
                    fputcsv($out,array_map(fn($value)=>is_string($value) && preg_match('/^[\s]*[=+@\-]/u',$value) ? "'".$value : $value,$values));
                }
                fclose($out);
            },"$type-report-$fileCurrency-{$v['from']}-{$v['to']}.csv",['Content-Type'=>'text/csv','Cache-Control'=>'no-store']);
        }
        return response()->json($report->query($type,$v))->header('Cache-Control','no-store');
    }
}
