<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\Http;

/** No retries. Upload and generation are independently claimed by the caller. */
class MarketingClipProvider {
 public function credits(float $required):array{
  abort_unless(filled(config('marketing.native.api_key')),422,'请先连接Evolink。');
  $r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(25)->withoutRedirecting()->get('https://api.evolink.ai/v1/credits');
  if(in_array($r->status(),[401,403]))throw new MarketingProductionFailure('connection_rejected','blocked');
  if(!$r->successful())throw new MarketingProductionFailure('credit_lookup_unavailable','blocked');
  $data=$r->json('data');$user=$data['user']['remaining_credits']??null;$token=$data['token']['remaining_credits']??null;
  if(!is_numeric($user)||(float)$user<$required||(!($data['token']['unlimited_credits']??false)&&(!is_numeric($token)||(float)$token<$required)))throw new MarketingProductionFailure('balance_topup','blocked');return $data;
 }
 public function upload(string $path):array{
  $r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(60)->withoutRedirecting()->attach('file',file_get_contents($path),'production-opening.png',['Content-Type'=>mime_content_type($path)])->post('https://files-api.evolink.ai/api/v1/files/upload/stream');
  if(!$r->successful()||$r->json('success')!==true)throw new MarketingProductionFailure('input_upload_unavailable','unknown');
  $f=$r->json('data');$parts=parse_url($f['file_url']??'');
  if(!$parts||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])&&$parts['port']!==443||!str_ends_with($parts['host']??'','.evolink.ai')||!isset($f['expires_at'])||now()->addMinutes(20)->gte(\Illuminate\Support\Carbon::parse($f['expires_at'])))throw new MarketingProductionFailure('input_upload_invalid','unknown');
  return \Illuminate\Support\Arr::only($f,['file_id','file_url','expires_at']);
 }
}
