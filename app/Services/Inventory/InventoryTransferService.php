<?php
namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function create(int $sourceId,int $destinationId,int $quantity,string $key,string $notes,User $actor): array
    {
        $this->authorize($actor);$this->key($key);
        if($sourceId===$destinationId||$quantity<1||$quantity>2147483647||mb_strlen($notes)>1000)$this->reject('Invalid transfer request.');
        $hash=hash('sha256',json_encode([$sourceId,$destinationId,$quantity,$notes,$actor->id],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($sourceId,$destinationId,$quantity,$key,$notes,$actor,$hash){
            $this->configuration();
            $existing=DB::table('inventory_transfers')->where('operation_key',$key)->first();
            if($existing){if(!hash_equals($existing->request_hash,$hash))$this->reject('Transfer identity conflicts with an earlier request.');return $this->project($existing);}
            $source=DB::table('inventory_balances')->find($sourceId);
            $destination=DB::table('inventory_balances')->find($destinationId);
            if(!$source||!$destination||$source->product_variant_id!==$destination->product_variant_id)$this->reject('Transfer must use the same SKU in two physical warehouses.');
            $this->warehouse($source);$this->warehouse($destination);
            $id=DB::table('inventory_transfers')->insertGetId(['operation_key'=>$key,'request_hash'=>$hash,
                'product_variant_id'=>$source->product_variant_id,'source_balance_id'=>$sourceId,'destination_balance_id'=>$destinationId,
                'quantity'=>$quantity,'actor_id'=>$actor->id,'notes'=>$notes,'created_at'=>now(),'updated_at'=>now()]);
            return $this->project(DB::table('inventory_transfers')->find($id));
        },3);
    }

    public function transition(int $id,string $action,int $quantity,string $key,int $expectedVersion,User $actor,string $notes=''): array
    {
        $this->authorize($actor);$this->key($key);
        if(mb_strlen($notes)>1000||!in_array($action,['dispatch','receive','cancel'],true)||$expectedVersion<0
            ||($action==='receive'?($quantity<1||$quantity>2147483647):$quantity!==0))$this->reject('Invalid transfer action.');
        $hash=hash('sha256',json_encode([$action,$quantity,$expectedVersion,$actor->id,$notes],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($id,$action,$quantity,$key,$expectedVersion,$actor,$hash,$notes){
            $this->configuration();
            $transfer=DB::table('inventory_transfers')->where('id',$id)->lockForUpdate()->first();
            if(!$transfer)$this->reject('Transfer does not exist.');
            $previous=DB::table('inventory_transfer_events')->where('transfer_id',$id)->where('operation_key',$key)->first();
            if($previous){if(!hash_equals($previous->request_hash,$hash))$this->reject('Transfer operation identity conflicts.');return json_decode($previous->receipt,true,512,JSON_THROW_ON_ERROR);}
            if((int)$transfer->version!==$expectedVersion)$this->reject('Transfer changed. Reload before continuing.');
            DB::table('product_variants')->where('id',$transfer->product_variant_id)->lockForUpdate()->first();
            $balances=DB::table('inventory_balances')->whereIn('id',[$transfer->source_balance_id,$transfer->destination_balance_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source=$balances[$transfer->source_balance_id];$destination=$balances[$transfer->destination_balance_id];
            $updates=['version'=>$expectedVersion+1,'updated_at'=>now()];$changed=null;$before=null;$after=null;
            if($action==='cancel'){
                if($transfer->status!=='draft')$this->reject('Only an undispatched draft can be cancelled. In-transit goods require a recorded return.');
                $updates['status']='cancelled';
            }elseif($action==='dispatch'){
                if($transfer->status!=='draft')$this->reject('Only a draft can be dispatched.');
                $this->warehouse($source);$this->warehouse($destination);
                $projection=app(PhysicalBalanceReader::class)->project($source);
                if(!$projection['verified']||$projection['atp']<(int)$transfer->quantity)$this->reject('Source warehouse has insufficient verified ATP.');
                $changed=$source;$quantity=(int)$transfer->quantity;$before=(int)$source->physical_stock;$after=$before-$quantity;
                $updates+=['status'=>'in_transit','dispatched_at'=>now()];
            }else{
                if($transfer->status!=='in_transit')$this->reject('Only in-transit goods can be received.');
                if($quantity>(int)$transfer->quantity-(int)$transfer->received_quantity)$this->reject('Receipt exceeds outstanding transfer quantity.');
                $this->warehouse($destination);
                if($destination->physical_stock===null||$destination->counted_at===null)$this->reject('Count the destination warehouse before receiving into its balance.');
                $changed=$destination;$before=(int)$destination->physical_stock;$after=$before+$quantity;
                if($after>2147483647)$this->reject('Destination stock exceeds the supported quantity.');
                $updates['received_quantity']=(int)$transfer->received_quantity+$quantity;
                if($updates['received_quantity']===(int)$transfer->quantity)$updates+=['status'=>'completed','completed_at'=>now()];
            }
            if($changed){
                if(DB::table('inventory_configuration')->where('id',1)->value('accounting_enabled')){
                    $reader=app(PhysicalBalanceReader::class);
                    $oldProjection=$reader->project($changed);
                    $newBalance=clone $changed;$newBalance->physical_stock=$after;
                    $newProjection=$reader->project($newBalance);
                    if(!$oldProjection['verified']||!$newProjection['verified'])$this->reject('Reconcile warehouse obligations before an active inventory transfer.');
                    $availableDelta=$newProjection['atp']-$oldProjection['atp'];
                    app(InventoryMutationService::class)->change((int)$transfer->product_variant_id,$availableDelta,
                        'transfer:'.$id.':'.($expectedVersion+1),'Warehouse transfer '.$action,(int)$actor->id);
                }
                DB::table('inventory_balances')->where('id',$changed->id)->update(['physical_stock'=>$after,'version'=>(int)$changed->version+1,'updated_at'=>now()]);
            }
            DB::table('inventory_transfers')->where('id',$id)->update($updates);
            $receipt=$this->project(DB::table('inventory_transfers')->find($id));
            $receipt['action_notes']=$notes;
            DB::table('inventory_transfer_events')->insert(['transfer_id'=>$id,'operation_key'=>$key,'request_hash'=>$hash,'action'=>$action,
                'quantity'=>$quantity,'actor_id'=>$actor->id,'balance_id'=>$changed?->id,'physical_before'=>$before,'physical_after'=>$after,
                'receipt'=>json_encode($receipt,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $receipt;
        },3);
    }

    private function configuration(): void
    {
        $config=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
        if(!$config||$config->operating_mode!=='independent_warehouses')$this->reject('Independent warehouse accounting must be configured before transfers.');
    }
    private function warehouse(object $balance): void
    {
        $pool=DB::table('inventory_pools')->find($balance->inventory_pool_id);
        if(!$pool||$pool->kind!=='warehouse'||!$pool->warehouse_id)$this->reject('Transfer requires physical warehouse balances.');
        $warehouse=DB::table('inventory_warehouses')->find($pool->warehouse_id);
        if(!$warehouse||$warehouse->status!=='active')$this->reject('Transfer warehouse must be active.');
    }
    private function authorize(User $actor): void
    {
        if(!$actor->exists||!User::whereKey($actor->id)->where('is_admin',true)->exists())$this->reject('An authenticated inventory administrator is required.');
    }
    private function key(string $key):void {if(!preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/',$key))$this->reject('Invalid operation identity.');}
    private function reject(string $message):never {throw ValidationException::withMessages(['transfer'=>$message]);}
    private function project(object $row):array
    {
        $data=(array)$row;unset($data['request_hash']);
        foreach(['id','product_variant_id','source_balance_id','destination_balance_id','quantity','received_quantity','version','actor_id']as $key)$data[$key]=(int)$data[$key];
        $data['in_transit_quantity']=$row->status==='in_transit'?(int)$row->quantity-(int)$row->received_quantity:0;
        return $data;
    }
}
