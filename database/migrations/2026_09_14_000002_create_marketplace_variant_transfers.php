<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {Schema::create('marketplace_variant_transfers',function(Blueprint $table){
        $table->id();$table->unsignedBigInteger('listing_variant_id');$table->unsignedBigInteger('product_variant_id');
        $table->unsignedBigInteger('from_product_id');$table->unsignedBigInteger('to_product_id');$table->unsignedBigInteger('actor_id');
        $table->string('reason',500);$table->timestamp('created_at');
    });}
    public function down():void {Schema::dropIfExists('marketplace_variant_transfers');}
};
