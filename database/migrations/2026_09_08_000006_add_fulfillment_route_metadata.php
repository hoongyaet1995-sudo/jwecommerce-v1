<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('inventory_fulfillment_routes', function(Blueprint $table) {
            $table->string('platform_location_name',255)->nullable();
            $table->unsignedInteger('fulfillment_priority')->default(1);
        });
    }
    public function down(): void {
        Schema::table('inventory_fulfillment_routes', fn(Blueprint $table)=>$table->dropColumn(['platform_location_name','fulfillment_priority']));
    }
};
