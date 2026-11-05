<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Reports\SalesReport;
use Illuminate\Http\Request;
class SalesReportController extends Controller {
 public function __invoke(Request $request, SalesReport $report) {
  $user=$request->user();
  $financial=$request->routeIs('admin.api.reports.financial');
  $permission=$financial?'reports.financial.view':'reports.sales.view';
  abort_unless($user->is_super_admin || ($user->is_admin && $user->permissions()->where('permission',$permission)->exists()),403);
  $v=$request->validate(['from'=>'sometimes|date_format:Y-m-d','to'=>'sometimes|date_format:Y-m-d|after_or_equal:from','currency'=>'sometimes|string|size:3|regex:/^[A-Z]{3}$/','page'=>'sometimes|integer|min:1','search'=>'nullable|string|max:100','export'=>'sometimes|in:csv']);
  $v+=['from'=>now('Asia/Kuala_Lumpur')->subDays(29)->toDateString(),'to'=>now('Asia/Kuala_Lumpur')->toDateString(),'currency'=>'MYR'];
  abort_if(\Carbon\CarbonImmutable::parse($v['from'])->diffInDays(\Carbon\CarbonImmutable::parse($v['to']),false)<0 || \Carbon\CarbonImmutable::parse($v['from'])->diffInDays(\Carbon\CarbonImmutable::parse($v['to']))>366,422,'Select a period of up to 367 days.');
  if(($v['export']??null)==='csv') {
   (clone $report->events($v))->limit(1)->get(); // Validate the read before download headers exist.
   return response()->streamDownload(function()use($report,$v){$out=fopen('php://output','w');fputcsv($out,['Order','Type','Date (UTC)','Currency','Amount (minor units)']);$report->events($v)->orderBy('occurred_at')->orderBy('kind')->orderBy('event_id')->chunk(500,function($rows)use($out,$v){foreach($rows as $r){$n=preg_match('/^[\s]*[=+@\-]/u',$r->number)?"'".$r->number:$r->number;fputcsv($out,[$n,$r->kind,$r->occurred_at,$v['currency'],$r->amount]);}});fclose($out);},'sales-report.csv',['Content-Type'=>'text/csv','Cache-Control'=>'no-store']);
  }
  $result=$report->query($v);
  if($financial)$result+=app(\App\Services\Reports\FinancialEvidence::class)->query($v,$report->firstPaidOrders($v),$result['as_of']);
  return response()->json($result)->header('Cache-Control','no-store');
 }
}
