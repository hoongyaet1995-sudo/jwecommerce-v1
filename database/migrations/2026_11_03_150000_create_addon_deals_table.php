<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('addon_deals',function(Blueprint $t){$t->id();$t->string('name');$t->text('description')->nullable();$t->unsignedInteger('per_order_cap')->default(1);$t->json('product_ids');$t->json('addons');$t->string('cover_image',2048)->nullable();$t->json('image_settings')->nullable();$t->timestamp('starts_at')->nullable();$t->timestamp('ends_at')->nullable();$t->timestamps();}); }
 public function down():void {Schema::dropIfExists('addon_deals');}
};
