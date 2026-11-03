<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('promotion_offers',function(Blueprint $t){$t->id();$t->string('name');$t->text('description')->nullable();$t->unsignedInteger('quantity');$t->unsignedBigInteger('price');$t->boolean('repeat')->default(false);$t->boolean('enabled')->default(false)->index();$t->timestamp('starts_at')->nullable();$t->timestamp('ends_at')->nullable();$t->timestamps();});
  Schema::create('promotion_offer_product',function(Blueprint $t){$t->foreignId('promotion_offer_id')->constrained()->cascadeOnDelete();$t->foreignId('product_id')->constrained()->cascadeOnDelete();$t->primary(['promotion_offer_id','product_id']);$t->index(['product_id','promotion_offer_id']);});
  Schema::table('orders',fn(Blueprint $t)=>$t->json('promotion_snapshot')->nullable());
 }
 public function down():void {Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn('promotion_snapshot'));Schema::dropIfExists('promotion_offer_product');Schema::dropIfExists('promotion_offers');}
};
