<?php
namespace App\Services\Support;
use App\Models\Coupon;
class DiscountKnowledgeService
{
    public function records(): array
    {
        if(!\Illuminate\Support\Facades\Schema::hasTable('coupons'))return [];
        return Coupon::query()->orderByDesc('id')->get()->map(fn ($c) => [
            'id'=>(string)$c->id, 'type'=>'Voucher', 'name'=>$c->name ?: $c->code, 'code'=>$c->code,
            'benefit'=>match($c->discount_type){'percentage'=>$c->value.'% OFF','fixed_amount'=>'RM '.number_format($c->value/100,2).' OFF','free_shipping'=>'Free shipping',default=>'Unsupported'},
            'status'=>$c->isCurrentlyValid()?'Live':(!$c->is_active?'Inactive':($c->starts_at?->isFuture()?'Scheduled':($c->ends_at?->isPast()?'Expired':'Limit reached'))),
            'minimumSpend'=>$c->min_spend===null?null:$c->min_spend/100, 'endsAt'=>$c->ends_at?->toIso8601String(),
            'perCustomerLimit'=>$c->per_customer_limit, 'description'=>mb_substr($c->description??'',0,1000),
        ])->all();
    }
    public function facts(array $scope, string $question = ""): array
    {
        if(!($scope['isEnabled']??false))return [];
        $allowed=$scope['activeIds']??[];
        // Exact code enquiries may inspect unavailable offers; browsing only recommends live offers.
        $records = array_values(array_filter($this->records(),fn($c)=>in_array($c['id'],$allowed,true)));
        $specified = array_values(array_filter($records, fn($c)=>preg_match('/(?<![\p{L}\p{N}_-])'.preg_quote($c['code'],'/').'(?![\p{L}\p{N}_-])/iu',$question)));
        $records = $specified ?: array_values(array_filter($records,fn($c)=>$c['status']==='Live'));
        return array_map(fn($c)=>['id'=>'discount:'.$c['id'],'question'=>'Official promotion '.$c['name'],'answer'=>json_encode($c+[
            'query_mode'=>$specified?'specific_code':'available_offers',
            'redeemable_now'=>$c['status']==='Live',
            'instruction'=>$c['status']==='Live'?'Explain published conditions only.':'This offer cannot currently be redeemed. Explain its real status and expiry date; do not recommend or promise redemption.',
            'eligibility'=>'Not verified for this customer. Checkout validates minimum spend, usage limits and order eligibility. No guarantee of applicability or stacking.'
        ],JSON_UNESCAPED_UNICODE)],array_slice($records,0,20));
    }
}
