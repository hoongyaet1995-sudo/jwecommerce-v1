<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('marketplace_stores','account_mode')) {
            Schema::table('marketplace_stores', function (Blueprint $table) {
                $table->string('account_mode',20)->default('production')->after('environment');
                $table->index(['platform','environment','account_mode'],'mp_store_mode_lookup');
            });
        }
        if (!Schema::hasTable('marketplace_store_capabilities')) {
            Schema::create('marketplace_store_capabilities', function (Blueprint $table) {
                $table->foreignId('store_id')->primary()->constrained('marketplace_stores')->cascadeOnDelete();
                $table->boolean('catalog_read')->default(true);
                $table->boolean('product_publish')->default(false);
                $table->boolean('order_receive')->default(false);
                $table->boolean('inventory_update')->default(false);
                $table->boolean('fulfillment_write')->default(false);
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('change_reason',255)->nullable();
                $table->timestamps();
            });
        }
        foreach (DB::table('marketplace_stores')->orderBy('id')->cursor() as $store) {
            DB::table('marketplace_store_capabilities')->updateOrInsert(['store_id'=>$store->id],[
                'store_id'=>$store->id,'catalog_read'=>$store->status==='connected',
                'product_publish'=>false,'order_receive'=>false,'inventory_update'=>false,
                'fulfillment_write'=>false,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('marketplace_store_capabilities');
        Schema::table('marketplace_stores', function (Blueprint $table) {
            $table->dropIndex('mp_store_mode_lookup');
            $table->dropColumn('account_mode');
        });
    }
};
