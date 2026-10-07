<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('support_customer_memories',function(Blueprint $t){
            $t->id();$t->string('merchant_key');$t->string('identity_hash',64);$t->string('trust_hash',64)->nullable();
            $t->unsignedBigInteger('customer_id');$t->uuid('source_conversation_id');$t->unsignedBigInteger('source_message_id');
            $t->string('kind',30);$t->text('content')->nullable();$t->timestamp('expires_at');$t->timestamp('revoked_at')->nullable();
            $t->unsignedBigInteger('staff_user_id')->nullable();$t->timestamps();
            $t->unique(['source_message_id','kind']);$t->index(['merchant_key','identity_hash','expires_at'],'memory_identity_expiry');
            $t->index(['merchant_key','customer_id','expires_at'],'memory_customer_expiry');
        });
        Schema::create('support_memory_extractions',function(Blueprint $t){$t->unsignedBigInteger('message_id')->primary();$t->string('status',20);$t->timestamps();});
    }
    public function down():void{Schema::dropIfExists('support_memory_extractions');Schema::dropIfExists('support_customer_memories');}
};
