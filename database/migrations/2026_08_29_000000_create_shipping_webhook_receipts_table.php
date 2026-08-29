<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->default('easyparcel');
            $table->string('environment', 24)->default('sandbox');
            $table->string('topic', 64);
            $table->string('provider_uuid', 160)->nullable();
            $table->char('payload_hash', 64);
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('correlation_snapshot');
            $table->string('processing_state', 24)->default('received');
            $table->string('last_error', 64)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'environment', 'payload_hash'], 'shipping_webhook_receipt_dedupe');
            $table->index(['processing_state', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_receipts');
    }
};
