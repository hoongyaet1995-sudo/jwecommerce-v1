<?php

namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRoutingService
{
    public function save(string $channel, int $warehouseId, ?int $version, ?string $location, bool $enabled, User $actor, ?string $locationName=null, int $priority=1): array
    {
        if (!$actor->exists || !User::whereKey($actor->id)->where('is_admin', true)->exists()) {
            $this->reject('An authenticated inventory administrator is required.');
        }
        if ($location !== null && mb_strlen($location) > 128) $this->reject('Platform location is too long.');
        if(($locationName!==null && mb_strlen($locationName)>255)||$priority<1||$priority>2147483647)$this->reject('Invalid fulfillment route metadata.');
        return DB::transaction(function () use ($channel, $warehouseId, $version, $location, $enabled, $actor, $locationName, $priority) {
            DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if ($channel !== 'website') {
                if (!preg_match('/\Amarketplace:([1-9][0-9]*)\z/', $channel, $match)
                    || !DB::table('marketplace_stores')->where('id', $match[1])->whereIn('platform', ['shopee', 'lazada'])->exists()) {
                    $this->reject('Choose an existing Shopee or Lazada store.');
                }
            }
            $warehouse = DB::table('inventory_warehouses')->where('id', $warehouseId)->lockForUpdate()->first();
            if (!$warehouse || ($enabled && $warehouse->status !== 'active')) $this->reject('An enabled route requires an active warehouse.');
            $existing = DB::table('inventory_fulfillment_routes')->where('channel_key', $channel)->lockForUpdate()->first();
            if (($existing && ($version === null || (int)$existing->version !== $version)) || (!$existing && $version !== null)) {
                $this->reject('Fulfillment route changed. Reload before saving.');
            }
            $data = ['warehouse_id'=>$warehouseId, 'platform_location_id'=>$channel === 'website' ? null : $location,
                'platform_location_name'=>$channel === 'website' ? null : $locationName, 'fulfillment_priority'=>$priority,
                'is_enabled'=>$enabled, 'version'=>$existing ? (int)$existing->version + 1 : 0, 'updated_at'=>now()];
            if ($existing) {
                DB::table('inventory_fulfillment_routes')->where('id', $existing->id)->update($data);
                $id = $existing->id;
            } else {
                $id = DB::table('inventory_fulfillment_routes')->insertGetId($data + ['channel_key'=>$channel, 'created_at'=>now()]);
            }
            $result=(array) DB::table('inventory_fulfillment_routes')->find($id);
            DB::table('inventory_route_changes')->insert(['route_id'=>$id,'actor_id'=>$actor->id,
                'before'=>$existing ? json_encode($existing,JSON_THROW_ON_ERROR) : null,
                'after'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $result;
        }, 3);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['route'=>$message]);
    }
}
