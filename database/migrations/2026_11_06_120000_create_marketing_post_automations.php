<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketing_post_automations',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('merchant_key');$t->string('name',120);$t->string('preset',40);$t->unsignedInteger('version')->default(1);$t->json('settings');$t->timestamps();$t->index(['user_id','merchant_key']);});
  Schema::create('marketing_automation_runs',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('automation_id')->constrained('marketing_post_automations')->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('merchant_key');$t->string('status');$t->json('snapshot');$t->json('selection')->nullable();$t->json('receipt')->nullable();$t->string('error_code')->nullable();$t->timestamps();$t->index(['automation_id','created_at']);});
 }
 public function down():void {Schema::dropIfExists('marketing_automation_runs');Schema::dropIfExists('marketing_post_automations');}
};
