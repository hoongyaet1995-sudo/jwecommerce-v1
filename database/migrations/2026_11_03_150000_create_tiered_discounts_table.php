<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('tiered_discounts',function(Blueprint $t){$t->id();$t->string('name');$t->text('description')->nullable();$t->boolean('enabled')->default(false);$t->string('scope')->default('all');$t->string('discount_type');$t->json('product_ids');$t->json('tiers');$t->timestamp('starts_at')->nullable();$t->timestamp('ends_at')->nullable();$t->timestamps();});}
 public function down():void {Schema::dropIfExists('tiered_discounts');}
};
