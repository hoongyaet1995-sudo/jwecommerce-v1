<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('managed_social_accounts',function(Blueprint $t){
            $t->boolean('inbound_enabled')->default(false);
            $t->timestamp('inbound_verified_at')->nullable();
            $t->timestamp('last_inbound_at')->nullable();
        });
        Schema::table('support_conversations',function(Blueprint $t){
            $t->string('channel',30)->default('web_guest');
            $t->string('merchant_key',100)->nullable();
            $t->string('provider_page_id',100)->nullable();
            $t->string('provider_sender_id',100)->nullable();
            $t->string('provider_page_name')->nullable();
        });
        Schema::create('messenger_inbound_receipts',function(Blueprint $t){
            $t->id();$t->string('event_key',64)->unique();$t->uuid('conversation_id');
            $t->string('page_id',100);$t->string('message_id',512);$t->timestamps();
            $t->index(['page_id','created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('messenger_inbound_receipts');
        Schema::table('support_conversations',fn(Blueprint $t)=>$t->dropColumn(['channel','merchant_key','provider_page_id','provider_sender_id','provider_page_name']));
        Schema::table('managed_social_accounts',fn(Blueprint $t)=>$t->dropColumn(['inbound_enabled','inbound_verified_at','last_inbound_at']));
    }
};
