<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketing_whatsapp_subscriptions',function(Blueprint $t){$t->uuid('id')->primary();$t->string('merchant_key',100);$t->string('phone_hash',64);$t->text('encrypted_phone');$t->string('status',20);$t->string('source',120);$t->timestamp('consented_at')->nullable();$t->timestamp('revoked_at')->nullable();$t->timestamps();$t->unique(['merchant_key','phone_hash']);});
  Schema::create('marketing_whatsapp_drafts',function(Blueprint $t){$t->uuid('id')->primary();$t->string('merchant_key',100);$t->foreignId('user_id')->constrained();$t->foreignUuid('campaign_id')->constrained('marketing_campaigns')->restrictOnDelete();$t->foreignUuid('template_id')->constrained('marketing_content_templates')->restrictOnDelete();$t->unsignedInteger('template_version');$t->json('document');$t->string('status',30)->default('Draft');$t->unsignedInteger('version')->default(1);$t->timestamps();$t->index(['merchant_key','user_id','campaign_id']);});
 }
 public function down():void {Schema::dropIfExists('marketing_whatsapp_drafts');Schema::dropIfExists('marketing_whatsapp_subscriptions');}
};
