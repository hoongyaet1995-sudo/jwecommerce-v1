<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void
    {
        Schema::table('inventory_configuration',fn(Blueprint $table)=>$table->boolean('accounting_enabled')->default(false));
        Schema::table('stock_allocations',fn(Blueprint $table)=>$table->foreignId('inventory_balance_id')->nullable()->constrained('inventory_balances')->restrictOnDelete());
        Schema::create('inventory_fulfillment_routes',function(Blueprint $table){
            $table->id();$table->string('channel_key',80)->unique();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->string('platform_location_id',128)->nullable();
            $table->boolean('is_enabled')->default(false);$table->unsignedBigInteger('version')->default(0);$table->timestamps();
        });
        Schema::create('inventory_allocation_movements',function(Blueprint $table){
            $table->id();$table->foreignId('stock_allocation_id')->constrained('stock_allocations')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->string('event',16);$table->integer('physical_delta');$table->integer('reserved_delta');
            $table->unsignedInteger('physical_after');$table->unsignedInteger('reserved_after');
            $table->unsignedBigInteger('balance_version');$table->dateTime('created_at');
            $table->unique(['stock_allocation_id','event'],'inventory_allocation_physical_event_unique');
        });
    }
    public function down():void
    {
        Schema::dropIfExists('inventory_allocation_movements');Schema::dropIfExists('inventory_fulfillment_routes');
        Schema::table('stock_allocations',fn(Blueprint $table)=>$table->dropConstrainedForeignId('inventory_balance_id'));
        Schema::table('inventory_configuration',fn(Blueprint $table)=>$table->dropColumn('accounting_enabled'));
    }
};
