<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easyparcel_reconciliation_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->default('easyparcel');
            $table->string('environment', 24)->default('sandbox');
            $table->string('task_key', 191)->unique();
            $table->string('type', 32);
            $table->foreignId('shipment_quote_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_webhook_receipt_id')->nullable();
            $table->foreign('shipping_webhook_receipt_id', 'ep_reconciliation_receipt_fk')
                ->references('id')
                ->on('shipping_webhook_receipts')
                ->cascadeOnDelete();
            $table->string('application_reference', 160)->nullable();
            $table->string('state', 24)->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('publication_attempt_count')->default(0);
            $table->unsignedSmallInteger('processing_attempt_count')->default(0);
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->string('last_error', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'available_at'], 'easyparcel_reconciliation_due');
            $table->index(['state', 'published_at'], 'easyparcel_reconciliation_publication');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easyparcel_reconciliation_tasks');
    }
};
