<?php
namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\MarketingPermission;
use App\Http\Controllers\Controller;
use App\Services\Reports\MarketingReportBridge;
use Illuminate\Http\Request;

final class MarketingReportBridgeController extends Controller
{
    public function __invoke(Request $request,MarketingReportBridge $bridge)
    {
        $user=$request->user();
        abort_unless($user->is_super_admin||($user->is_admin&&$user->permissions()->where('permission','reports.marketing.view')->exists()),403);
        abort_unless(MarketingPermission::allows($user,MarketingPermission::VIEW_ANALYTICS),403);
        $v=$request->validate(['from'=>'sometimes|date_format:Y-m-d','to'=>'sometimes|date_format:Y-m-d','currency'=>'sometimes|string|size:3|regex:/^[A-Z]{3}$/','page'=>'sometimes|integer|min:1|max:10000','search'=>'nullable|string|max:100','export'=>'sometimes|in:csv']);
        $v+=['from'=>now('Asia/Kuala_Lumpur')->subDays(29)->toDateString(),'to'=>now('Asia/Kuala_Lumpur')->toDateString(),'currency'=>'MYR'];
        abort_if($v['from']>$v['to'],422,'From date must not be after to date.');
        $data=$bridge->query($request,$v);$rows=$data['export_rows'];unset($data['export_rows']);
        if(($v['export']??null)==='csv')return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');$fields=MarketingReportBridge::EXPORT_FIELDS;fputcsv($out,$fields);foreach($rows as $row)fputcsv($out,array_map(function($key)use($row){$value=$row[$key];return is_string($value)&&preg_match('/^[\s]*[=+@\-]/u',$value)?"'".$value:$value;},$fields));fclose($out);},"marketing-{$v['currency']}-{$v['from']}-{$v['to']}.csv",['Content-Type'=>'text/csv','Cache-Control'=>'private, no-store']);
        return response()->json($data)->header('Cache-Control','private, no-store');
    }
}
