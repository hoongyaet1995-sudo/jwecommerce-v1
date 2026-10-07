<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_attachment_analyses', function(Blueprint $t) {
            $t->id();
            $t->uuid('attachment_id');
            $t->unsignedBigInteger('source_message_id');
            $t->string('merchant_key',100)->index();
            $t->string('kind',16);
            $t->char('source_hash',64);
            $t->unsignedInteger('settings_version');
            $t->char('attempt_key',64)->unique();
            $t->string('status',16)->default('pending');
            $t->text('result')->nullable();
            $t->string('provider',32)->nullable();
            $t->string('model',150)->nullable();
            $t->string('failure_code',40)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
            $t->foreign('attachment_id')->references('id')->on('support_attachments')->cascadeOnDelete();
            $t->foreign('source_message_id')->references('id')->on('support_messages')->cascadeOnDelete();
            $t->index(['source_message_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('support_attachment_analyses'); }
};
