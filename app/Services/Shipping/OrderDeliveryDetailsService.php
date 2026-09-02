<?php
namespace App\Services\Shipping;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderAddressAmendment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OrderDeliveryDetailsService {
    public function amend(Order $order, User $actor, array $input): array {
        return DB::transaction(function () use($order,$actor,$input): array {
            $lockedOrder=Order::query()->with('customer')->lockForUpdate()->findOrFail($order->id);
            if($lockedOrder->status==='cancelled') throw ValidationException::withMessages(['order'=>'Cancelled order delivery details are immutable.']);
            if($lockedOrder->status==='delivered') throw ValidationException::withMessages(['order'=>'Delivered orders must use the return or reshipment workflow.']);
            $address=OrderAddress::query()->where('order_id',$lockedOrder->id)->where('type','shipping')->lockForUpdate()->firstOrFail();
            $before=$address->only(['first_name','last_name','phone','line_1','line_2','city','state','postal_code','country']);
            $after=['first_name'=>trim($input['first_name']),'last_name'=>trim($input['last_name']),'phone'=>trim($input['phone']),'line_1'=>trim($input['line_1']),'line_2'=>filled($input['line_2']??null)?trim($input['line_2']):null,'city'=>trim($input['city']),'state'=>trim($input['state']),'postal_code'=>trim($input['postal_code']),'country'=>trim($input['country'])];
            if($before===$after) throw ValidationException::withMessages(['details'=>'At least one delivery detail must change.']);
            $active=Shipment::query()->where('order_id',$lockedOrder->id)->whereNotIn('status',['cancelled','delivered','returned'])->lockForUpdate()->get();
            $requiresReplacement=$active->contains(fn(Shipment $shipment):bool=>$shipment->source==='easyparcel');
            $address->forceFill($after)->save();
            foreach($active->where('source','manual') as $shipment)$shipment->forceFill(['destination_snapshot'=>$after])->save();
            $syncResult='not_requested';
            if($input['sync_address_book']){
                $saved=$lockedOrder->customer===null?null:CustomerAddress::query()->where('customer_id',$lockedOrder->customer_id)->where('line_1',$before['line_1'])->where('postal_code',$before['postal_code'])->lockForUpdate()->first();
                if($saved===null)$syncResult='no_matching_address'; else{$saved->forceFill($after)->save();$syncResult='updated';}
            }
            OrderAddressAmendment::query()->create(['order_id'=>$lockedOrder->id,'order_address_id'=>$address->id,'before_snapshot'=>$before,'after_snapshot'=>$after,'reason_code'=>$input['reason_code'],'internal_note'=>$input['internal_note'],'address_book_sync_requested'=>$input['sync_address_book'],'address_book_sync_result'=>$syncResult,'created_by'=>$actor->id]);
            return ['order'=>$lockedOrder->fresh(),'address'=>$address->fresh(),'address_book_sync_result'=>$syncResult,'requires_replacement'=>$requiresReplacement];
        });
    }
}
