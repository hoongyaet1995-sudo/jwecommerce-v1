<?php
namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryWarehouseService
{
    public function save(?int $id, ?int $expectedVersion, array $input, User $actor): array
    {
        if (!$actor->exists || !User::query()->whereKey($actor->id)->where('is_admin',true)->exists()) {
            $this->reject('An authenticated inventory administrator is required.');
        }
        $data=Validator::make($input,[
            'code'=>'required|string|max:64|regex:/\A[A-Za-z0-9_-]+\z/',
            'name'=>'required|string|max:255','status'=>'required|in:active,inactive,maintenance',
            'is_default_master'=>'required|boolean','address'=>'nullable|string|max:2000',
            'state'=>'nullable|string|max:255','contact_person'=>'nullable|string|max:255',
            'contact_phone'=>'nullable|string|max:64','notes'=>'nullable|string|max:2000',
            'supported_carriers'=>'sometimes|array|max:30','supported_carriers.*'=>'string|max:100|distinct',
        ])->validate();
        if ($data['is_default_master'] && $data['status']!=='active') $this->reject('The default warehouse must be active.');
        return DB::transaction(function()use($id,$expectedVersion,$data,$actor){
            $config=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            if (!$config) $this->reject('Inventory configuration is unavailable.');
            $before=$id===null?null:DB::table('inventory_warehouses')->where('id',$id)->lockForUpdate()->first();
            if ($id!==null && (!$before || $expectedVersion===null || (int)$before->version!==$expectedVersion)) {
                $this->reject('Warehouse changed. Reload before saving.');
            }
            $code=strtoupper($data['code']);
            $duplicate=DB::table('inventory_warehouses')->where('code',$code);
            if ($id!==null) $duplicate->where('id','<>',$id);
            if ($duplicate->exists()) $this->reject('Warehouse code is already in use.');
            $values=$data;unset($values['is_default_master']);
            if(array_key_exists('supported_carriers',$values))$values['supported_carriers']=json_encode($values['supported_carriers'],JSON_THROW_ON_ERROR);
            $values+=['updated_at'=>now()];
            $values['code']=$code;
            $values['is_active']=$data['status']==='active';
            $values['version']=$before?(int)$before->version+1:0;
            if ($before) DB::table('inventory_warehouses')->where('id',$id)->update($values);
            else $id=DB::table('inventory_warehouses')->insertGetId($values+['created_at'=>now()]);
            $default=$data['is_default_master']?$id:((int)$config->default_warehouse_id===$id?null:$config->default_warehouse_id);
            if ($config->default_warehouse_id!==null && (int)$config->default_warehouse_id!==$id
                && $default!==$config->default_warehouse_id) {
                DB::table('inventory_warehouses')->where('id',$config->default_warehouse_id)->increment('version',1,['updated_at'=>now()]);
            }
            DB::table('inventory_configuration')->where('id',1)->update(['default_warehouse_id'=>$default,'version'=>(int)$config->version+1]);
            $after=(array)DB::table('inventory_warehouses')->find($id);
            DB::table('inventory_warehouse_changes')->insert(['warehouse_id'=>$id,'actor_id'=>$actor->id,
                'before'=>$before?json_encode(['warehouse'=>$before,'default_warehouse_id'=>$config->default_warehouse_id],JSON_THROW_ON_ERROR):null,
                'after'=>json_encode(['warehouse'=>$after,'default_warehouse_id'=>$default],JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $after+['is_default_master'=>$default===$id];
        },3);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['warehouse'=>$message]);
    }
}
