<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_warehouses',function(Blueprint $table){$table->string('status',16)->default('inactive');});
        DB::table('inventory_warehouses')->where('is_active',true)->update(['status'=>'active']);
        Schema::create('inventory_configuration',function(Blueprint $table){
            $table->unsignedInteger('id')->primary();
            $table->string('operating_mode',32)->nullable();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(0);
        });
        DB::table('inventory_configuration')->insert(['id'=>1]);
        Schema::create('inventory_warehouse_changes',function(Blueprint $table){
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('before')->nullable();
            $table->json('after');
            $table->dateTime('created_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouse_changes');
        Schema::dropIfExists('inventory_configuration');
        Schema::table('inventory_warehouses',fn(Blueprint $table)=>$table->dropColumn('status'));
    }
};
