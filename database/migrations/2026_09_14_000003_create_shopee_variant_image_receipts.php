<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::create('shopee_variant_image_receipts',function(Blueprint $table){
        $table->id();$table->unsignedBigInteger('store_id');$table->unsignedBigInteger('product_id');
        $table->char('source_hash',64);$table->string('provider_identity');$table->unsignedBigInteger('actor_id');
        $table->timestamps();$table->unique(['store_id','product_id','source_hash'],'shopee_variant_image_source');
    });}
    public function down(): void {Schema::dropIfExists('shopee_variant_image_receipts');}
};
