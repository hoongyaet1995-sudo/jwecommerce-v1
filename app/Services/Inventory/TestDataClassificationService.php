<?php
namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Explicit test provenance; classification alone never grants provider write permission. */
final class TestDataClassificationService
{
    private const TABLES=['store'=>'marketplace_stores','sku'=>'product_variants',
        'warehouse'=>'inventory_warehouses','order'=>'orders','marketplace_order'=>'marketplace_orders'];

    public function markTest(string $type,int $id,User $actor,string $reason):array
    {
        abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS is disabled.');
        abort_unless($actor->exists&&User::whereKey($actor->id)->where('is_admin',true)->exists(),403);
        abort_unless(isset(self::TABLES[$type])&&$id>0&&trim($reason)!==''&&mb_strlen($reason)<=255,422,'Review test classification identity and reason.');
        return DB::transaction(function()use($type,$id,$actor,$reason){
            $table=self::TABLES[$type];
            $row=DB::table($table)->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            abort_unless(in_array($row->data_classification,['unclassified','test'],true),409,'Operational data cannot be relabeled as test data.');
            if($row->data_classification==='unclassified')DB::table($table)->where('id',$id)->update([
                'data_classification'=>'test','classification_reason'=>trim($reason),'classified_by'=>$actor->id,'classified_at'=>now()]);
            if($type==='store'){
                $store=DB::table($table)->find($id);
                DB::table('marketplace_orders')->where('store_id',$id)->where('data_classification','unclassified')->update($this->inherited($store));
            }
            $saved=DB::table($table)->find($id);
            return ['type'=>$type,'id'=>$id,'classification'=>$saved->data_classification,
                'actor_id'=>$saved->classified_by,'reason'=>$saved->classification_reason,'timestamp'=>$saved->classified_at];
        });
    }

    public function inherited(object $store):array
    {
        if(($store->data_classification??null)!=='test'||!$store->classified_by||!$store->classified_at)return [];
        return ['data_classification'=>'test','classified_by'=>$store->classified_by,'classified_at'=>now(),
            'classification_reason'=>'Inherited explicit test classification from marketplace store #'.$store->id];
    }

    public function inheritVariant(int $variantId,int $storeId):void
    {
        $store=DB::table('marketplace_stores')->find($storeId);
        $values=$store?$this->inherited($store):[];
        if($values)DB::table('product_variants')->where('id',$variantId)->where('data_classification','unclassified')->update($values);
    }
}
