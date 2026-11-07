<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('marketing_instant_post_runs',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('merchant_key');$t->string('status');$t->json('snapshot');$t->json('selection')->nullable();$t->json('receipt')->nullable();$t->string('error_code')->nullable();$t->timestamps();$t->index(['user_id','merchant_key','created_at'],'marketing_instant_owner');});}
 public function down():void {Schema::dropIfExists('marketing_instant_post_runs');}
};
