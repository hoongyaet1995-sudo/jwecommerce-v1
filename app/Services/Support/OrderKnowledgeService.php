<?php
namespace App\Services\Support;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTrackingVerification;
use App\Models\SupportConversation;
class OrderKnowledgeService {
 public function reply(string $question,array $scope,?SupportConversation $conversation,string $language='en'):string{
 return $this->outcome($question,$scope,$conversation,$language)['answer'];
 }
 public function outcome(string $question,array $scope,?SupportConversation $conversation,string $language='en'):array{
 if(!($scope['isEnabled']??false))return ['status'=>'disabled','answer'=>''];
 $verify=['en'=>'Please sign in to your customer account, or verify your order email code on the Order Tracking page before I can access order details.','ms'=>'Sila log masuk ke akaun pelanggan atau sahkan kod e-mel pada halaman Order Tracking sebelum butiran pesanan boleh diakses.','zh'=>'请先登录客户账户，或在 Order Tracking 页面完成订单邮箱验证码验证，然后才能查询订单资料。'];
 if(!$conversation || in_array($conversation->channel,['facebook','instagram','whatsapp'],true))return ['status'=>'verification_required','answer'=>$verify[$language]];
 if(!request()->hasSession())return ['status'=>'verification_required','answer'=>$verify[$language]];
 [$owner,$userId]=app(SupportChatService::class)->owner(request());
 if(!hash_equals($conversation->owner_hash,$owner)||$conversation->customer_user_id!==$userId)return ['status'=>'verification_required','answer'=>$verify[$language]];
 $q=Order::query();$authorized=false;
 if($userId){$customer=Customer::where('user_id',$userId)->first();if($customer){$q->where('customer_id',$customer->id);$authorized=true;}}
 if(!$authorized){$attemptId=request()->session()->get('order_tracking.verified_attempt_id');
 $attempt=$attemptId?OrderTrackingVerification::where('public_id',$attemptId)->whereNotNull('verified_at')->where('verified_at','>=',now()->subMinutes(15))->first():null;
 if(!$attempt)return ['status'=>'verification_required','answer'=>$verify[$language]];$q->whereKey($attempt->order_id);$authorized=true;
 }
 // Search only the authorized set. Never look up a foreign order to reveal whether it exists.
 preg_match_all('/[\p{L}\p{N}_-]*\d[\p{L}\p{N}_-]*/u',$question,$matches);
 if($matches[0])$q->where(function($q)use($matches){foreach(array_slice($matches[0],0,5) as $number)$q->orWhereRaw('upper(number) = ?', [mb_strtoupper($number)]);});
 $orders=$q->orderByDesc('id')->limit(20)->get();
 $selected=$orders->filter(fn($o)=>preg_match('/(?<![\p{L}\p{N}_-])'.preg_quote($o->number,'/').'(?![\p{L}\p{N}_-])/iu',$question));
 if($selected->isEmpty()){
 if($orders->count()===1&&!preg_match('/\d/u',$question))$selected=$orders;
 else return ['status'=>'needs_reference','answer'=>['en'=>'Please provide the order number for an order in your verified account. I cannot confirm other orders.','ms'=>'Sila berikan nombor pesanan dalam akaun yang disahkan. Pesanan lain tidak dapat disahkan.','zh'=>'请提供已验证账户中的订单编号，其他订单无法确认。'][$language]];
 }
 $fields=$scope['allowedFields']??[];$out=[];
 foreach($selected->take(1) as $order){
 $lines=[$order->number];
 if($fields['orderStatus']??false)$lines[]= ['en'=>'Status','ms'=>'Status','zh'=>'状态'][$language].': '.$order->status;
 if($fields['trackingNumberAndCourier']??false)$lines[]=['en'=>'Courier / tracking','ms'=>'Kurier / penjejakan','zh'=>'快递／运单'][$language].': '.($order->tracking_carrier?:'—').' / '.($order->tracking_number?:'—');
 if($fields['estimatedDeliveryDate']??false)$lines[]=['en'=>'Estimated delivery: not confirmed.','ms'=>'Anggaran penghantaran: belum disahkan.','zh'=>'预计送达日期：尚未确认。'][$language];
 if($fields['itemSummary']??false)foreach($order->items()->limit(10)->get() as $i)$lines[]=$i->title.' · '.($i->variant_title??'').' × '.$i->quantity;
 if($fields['shippingAddressMasked']??false){$address=$order->addresses()->where('type','shipping')->first();if($address)$lines[]=['en'=>'Shipping region','ms'=>'Wilayah penghantaran','zh'=>'配送地区'][$language].': '.($address->state??'').' / '.($address->country??'');}
 if($fields['paymentStatus']??false){$payment=$order->payments()->latest('id')->first();if($payment)$lines[]=['en'=>'Payment','ms'=>'Bayaran','zh'=>'付款'][$language].': '.$payment->gateway.' / '.$payment->status;}
 if(count($lines)===1)$lines[]=['en'=>'Order details are not enabled for disclosure in chat.','ms'=>'Butiran pesanan tidak dibenarkan dalam sembang.','zh'=>'后台尚未开放聊天中的订单字段。'][$language];
 $out[]=implode("\n",$lines);
 }
 return ['status'=>'success','answer'=>implode("\n\n",$out)];
 }
}

