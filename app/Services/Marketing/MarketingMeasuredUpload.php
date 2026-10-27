<?php
namespace App\Services\Marketing;
use App\Models\MarketingStoryboardProviderCall;
use Illuminate\Support\Facades\Http;

/** Called only inside an already committed provider claim. Uncertain uploads never retry. */
class MarketingMeasuredUpload {
 public function balance():array {
  $r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(25)->withoutRedirecting()->get('https://api.evolink.ai/v1/credits');
  abort_unless($r->successful(),409,'积分核对失败，暂停后续调用。');$d=$r->json('data');
  foreach([$d['user']['remaining_credits']??null,$d['user']['used_credits']??null,$d['token']['used_credits']??null] as $v)abort_unless(is_numeric($v)&&is_finite((float)$v),409,'积分依据不完整，暂停。');return $d;
 }
 public function cost(array $before,array $after,float $rate):?int {
  foreach([$before['user']['remaining_credits']??null,$after['user']['remaining_credits']??null,$before['user']['used_credits']??null,$after['user']['used_credits']??null,$before['token']['used_credits']??null,$after['token']['used_credits']??null] as $v)if(!is_numeric($v)||!is_finite((float)$v))return null;
  $a=round($before['user']['remaining_credits']-$after['user']['remaining_credits'],6);$b=round($after['user']['used_credits']-$before['user']['used_credits'],6);$c=round($after['token']['used_credits']-$before['token']['used_credits'],6);
  return $a>=0&&abs($a-$b)<0.000001&&abs($a-$c)<0.000001&&is_finite($rate)&&$rate>0?(int)ceil($a*$rate*1000000):null;
 }
 public function upload(string $path,MarketingStoryboardProviderCall $claim,array $pricing):array {
  $before=$this->balance();$claim->update(['result'=>['before'=>$before]]);
  $upload=app(MarketingClipProvider::class)->upload($path);
  $claim->update(['result'=>['before'=>$before,'upload'=>$upload]]); // Persist link before the second account lookup.
  $after=$this->balance();$actual=$this->cost($before,$after,(float)$pricing['credit_usd']);
  return $upload+['before'=>$before,'after'=>$after,'actual_microusd'=>$actual,'measured_upload'=>true];
 }
}
