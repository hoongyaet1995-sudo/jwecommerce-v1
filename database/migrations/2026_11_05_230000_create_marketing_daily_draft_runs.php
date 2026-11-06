<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('marketing_daily_draft_runs',function(Blueprint $t){
  $t->uuid('id')->primary();$t->foreignId('user_id')->constrained('users');$t->string('merchant_key');
  $t->string('status');$t->json('snapshot');$t->json('creative')->nullable();$t->json('receipts')->nullable();$t->json('post_ids')->nullable();$t->string('error_code')->nullable();$t->timestamps();$t->index(['user_id','created_at']);
 }); }
 public function down(): void { Schema::dropIfExists('marketing_daily_draft_runs'); }
};
