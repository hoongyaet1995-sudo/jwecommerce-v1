<?php
namespace App\Services\Marketing\Audience;
use App\Models\PaymentWebhookEvent;
class AudiencePaymentEvidence {
 public function paid($order,$events=null):array {
  foreach($order->payments->sortByDesc('id') as $p){
   if(!in_array($p->status,['paid','partially_refunded','refunded'],true)||$p->currency!==$order->currency||(int)$p->amount!==(int)$order->total)continue;
   $at=null;
   if($p->gateway==='bank_transfer'&&$p->environment==='offline'&&$p->proof_status==='verified'&&$p->proof_reviewed_at)$at=$p->proof_reviewed_at;
   elseif($p->environment==='live')foreach(($events??PaymentWebhookEvent::where('order_payment_id',$p->id)->whereNotNull('verified_at')->get())->where('order_payment_id',$p->id)->where('provider',$p->gateway)->where('environment','live') as $w){$d=$w->normalized_payload;$amount=null;$currency=null;
    if($p->gateway==='stripe'&&($d['type']??'')==='checkout.session.completed'&&data_get($d,'data.object.payment_status')==='paid'){$amount=data_get($d,'data.object.amount_total');$currency=data_get($d,'data.object.currency');}
    elseif($p->gateway==='billplz'&&($d['status']??'')==='paid'){$amount=$d['amount']??null;$currency='MYR';}
    elseif($p->gateway==='paypal'&&($d['event_type']??'')==='PAYMENT.CAPTURE.COMPLETED'){$v=data_get($d,'resource.amount.value');if(is_string($v)&&preg_match('/^([0-9]{1,10})\.([0-9]{2})$/D',$v,$m))$amount=(int)$m[1]*100+(int)$m[2];$currency=data_get($d,'resource.amount.currency_code');}
    if(ctype_digit((string)$amount)&&(int)$amount===(int)$p->amount&&is_string($currency)&&strtoupper($currency)===$p->currency){$at=$w->verified_at;break;}}
   if($at){$refund=(int)$order->refunds->where('status','completed')->sum('amount');if($refund>(int)$p->amount)throw new \RuntimeException('Refund evidence mismatch');return ['currency'=>$p->currency,'paid_minor'=>(int)$p->amount,'refunded_minor'=>$refund,'occurred_at'=>$at];}
  }return ['currency'=>$order->currency,'paid_minor'=>0,'refunded_minor'=>0,'occurred_at'=>null];
 }
}
