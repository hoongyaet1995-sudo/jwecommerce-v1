<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_id');
            $table->string('environment');
            $table->text('credentials');
            $table->timestamps();
            $table->unique(['gateway_id', 'environment']);
        });

        Schema::table('order_payments', function (Blueprint $table): void {
            $table->string('provider')->nullable()->after('gateway');
            $table->string('environment')->nullable()->after('provider');
            $table->string('idempotency_key')->nullable()->after('reference');
            $table->unsignedBigInteger('amount')->nullable()->after('idempotency_key');
            $table->string('currency', 3)->nullable()->after('amount');
            $table->json('configuration_snapshot')->nullable()->after('payload');
            $table->timestamp('expires_at')->nullable()->after('configuration_snapshot');
            $table->unique(['gateway', 'reference'], 'order_payments_gateway_reference_unique');
            $table->unique('idempotency_key', 'order_payments_idempotency_key_unique');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('environment');
            $table->text('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');

        Schema::table('order_payments', function (Blueprint $table): void {
            $table->dropUnique('order_payments_gateway_reference_unique');
            $table->dropUnique('order_payments_idempotency_key_unique');
            $table->dropColumn(['provider', 'environment', 'idempotency_key', 'amount', 'currency', 'configuration_snapshot', 'expires_at']);
        });

        Schema::dropIfExists('payment_gateway_credentials');
    }
};
