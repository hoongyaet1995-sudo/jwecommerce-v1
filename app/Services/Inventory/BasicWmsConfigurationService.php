<?php
namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Initial setup only. Never converts quantities or enables order accounting. */
class BasicWmsConfigurationService
{
    public function configure(string $mode,int $expectedVersion,string $reason,string $key,User $actor):array
    {
        abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS is disabled.');
        abort_unless($actor->exists && User::whereKey($actor->id)->where('is_admin',true)->exists(),403);
        if(!in_array($mode,['shared_pool','independent_warehouses'],true)||$expectedVersion<0
            ||trim($reason)===''||mb_strlen($reason)>255||!Str::isUuid($key)) $this->reject('Review the initial mode and reason.');
        $hash=hash('sha256',json_encode([$mode,$expectedVersion,trim($reason)],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($mode,$expectedVersion,$reason,$key,$actor,$hash){
            $configuration=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            if(!$configuration)$this->reject('Inventory configuration is missing.');
            $previous=DB::table('basic_wms_configuration_events')->where('operation_key',$key)->first();
            if($previous){
                if((int)$previous->actor_id!==(int)$actor->id||!hash_equals($previous->request_hash,$hash))
                    $this->reject('This operation key belongs to another setup request.');
                return json_decode($previous->receipt,true,512,JSON_THROW_ON_ERROR);
            }
            if($configuration->operating_mode!==null||$configuration->accounting_enabled)
                $this->reject('Inventory is already configured. Mode conversion is unavailable in Basic WMS.');
            if((int)$configuration->version!==$expectedVersion)$this->reject('Configuration changed. Reload before setup.');
            $kind=$mode==='shared_pool'?'shared':'warehouse';
            if(DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
                ->where('p.kind','!=',$kind)->exists())
                $this->reject('Existing staged balances conflict with this mode. Reconcile pool ownership first.');
            if(DB::table('inventory_transfers')->whereIn('status',['draft','in_transit'])->exists())
                $this->reject('Resolve open transfers before initial setup.');
            $receipt=['operation_key'=>$key,'operating_mode'=>$mode,'configuration_version'=>$expectedVersion+1,
                'accounting_enabled'=>false,'actor_id'=>(int)$actor->id,'reason'=>trim($reason),'created_at'=>now()->toIso8601String()];
            DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>$mode,'version'=>$expectedVersion+1]);
            DB::table('basic_wms_configuration_events')->insert(['operation_key'=>$key,'actor_id'=>$actor->id,
                'request_hash'=>$hash,'receipt'=>json_encode($receipt,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $receipt;
        },3);
    }

    private function reject(string $message):never
    {
        throw ValidationException::withMessages(['mode'=>$message]);
    }
}
