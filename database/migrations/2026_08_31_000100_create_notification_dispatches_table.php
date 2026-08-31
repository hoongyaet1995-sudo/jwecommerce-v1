<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32);
            $table->string('event_key', 64);
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->string('idempotency_key', 512)->nullable()->unique();
            $table->string('recipient_email', 320);
            $table->string('recipient_name')->nullable();
            $table->string('rendered_subject');
            $table->string('rendered_preheader')->default('');
            $table->string('rendered_heading')->default('');
            $table->text('rendered_body');
            $table->string('rendered_cta_text', 120)->default('');
            $table->string('rendered_cta_url', 2048)->default('');
            $table->unsignedInteger('template_version')->nullable();
            $table->json('variable_snapshot');
            $table->string('state', 32)->index();
            $table->string('source', 32)->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->uuid('delivery_lease_token')->nullable();
            $table->timestamp('delivery_lease_expires_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_code', 32)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resend_of_id')->nullable()->constrained('notification_dispatches')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['state', 'queued_at']);
            $table->index(['state', 'delivery_lease_expires_at']);
            $table->index(['event_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
