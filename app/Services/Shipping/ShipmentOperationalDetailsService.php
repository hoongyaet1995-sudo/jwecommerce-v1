<?php
namespace App\Services\Shipping;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentOperationalAmendment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ShipmentOperationalDetailsService {
    public function amend(Shipment $shipment, User $actor, array $input): Shipment {
        return DB::transaction(function () use ($shipment,$actor,$input): Shipment {
            $locked=Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($locked->source==='easyparcel') throw ValidationException::withMessages(['shipment'=>'EasyParcel operational details are provider-owned; void and replace this attempt.']);
            if ($locked->status==='delivered'||$locked->delivered_at!==null) throw ValidationException::withMessages(['shipment'=>'Delivered shipment details are immutable.']);
            if ($locked->status==='cancelled') throw ValidationException::withMessages(['shipment'=>'Cancelled shipment details are immutable.']);
            $fields=$locked->fulfillment_method==='manual_delivery'?['driver_name','handoff_reference']:['carrier_code','carrier_name','service_name','tracking_number','tracking_url'];
            $before=$locked->only($fields); $updates=[];
            foreach($fields as $field) $updates[$field]=array_key_exists($field,$input)?$this->clean($input[$field]):$locked->getAttribute($field);
            if($before===$updates) throw ValidationException::withMessages(['details'=>'At least one operational detail must change.']);
            if(filled($updates['tracking_number']??null)&&Shipment::query()->where('tracking_number',$updates['tracking_number'])->whereKeyNot($locked->id)->exists()) throw ValidationException::withMessages(['trackingNumber'=>'This tracking number is already assigned to another shipment.']);
            $locked->forceFill($updates)->save();
            ShipmentOperationalAmendment::query()->create(['shipment_id'=>$locked->id,'before_snapshot'=>$before,'after_snapshot'=>$updates,'reason_code'=>$input['reason_code'],'internal_note'=>$input['internal_note'],'created_by'=>$actor->id]);
            ShipmentEvent::query()->create(['shipment_id'=>$locked->id,'status'=>$locked->status,'occurred_at'=>now(),'description'=>'Operational details corrected: '.$input['internal_note'],'source'=>'manual','created_by'=>$actor->id]);
            return $locked->fresh(['events']);
        });
    }
    private function clean(mixed $value): ?string { if($value===null)return null; $value=trim((string)$value); return $value===''?null:$value; }
}
