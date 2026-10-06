<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('support_attachments', function(Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('conversation_id')->index();
            $t->unsignedBigInteger('message_id')->nullable()->index();
            $t->string('owner_hash',64);
            $t->unsignedBigInteger('staff_user_id')->nullable();
            $t->string('merchant_key')->nullable();
            $t->string('disk')->default('support-private');
            $t->string('path')->nullable();
            $t->string('name'); $t->string('mime')->nullable();
            $t->unsignedInteger('bytes')->default(0);
            $t->unsignedInteger('width')->nullable(); $t->unsignedInteger('height')->nullable();
            $t->string('sha256',64)->nullable();
            $t->string('status')->default('ready');
            $t->unsignedBigInteger('account_id')->nullable();
            $t->text('provider_reference')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
            $t->foreign('conversation_id')->references('id')->on('support_conversations')->cascadeOnDelete();
            $t->foreign('message_id')->references('id')->on('support_messages')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('support_attachments'); }
};
