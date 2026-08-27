<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_provider_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 24);
            $table->unsignedBigInteger('provider_configuration_version');
            $table->json('package_snapshot');
            $table->json('request_snapshot');
            $table->json('response_snapshot');
            $table->string('selected_service_code', 10)->nullable();
            $table->string('idempotency_reference', 120)->unique();
            $table->string('booking_state', 24)->default('available');
            $table->string('booking_claim_key', 160)->nullable()->unique();
            $table->string('last_error', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('booking_started_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'provider', 'expires_at']);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('shipment_quote_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('provider_order_reference', 160)->nullable()->unique();
            $table->string('tracking_url', 2048)->nullable();
            $table->json('quote_snapshot')->nullable();
            $table->json('package_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipment_quote_id');
            $table->dropUnique(['provider_order_reference']);
            $table->dropColumn([
                'provider_order_reference',
                'tracking_url',
                'quote_snapshot',
                'package_snapshot',
            ]);
        });

        Schema::dropIfExists('shipment_quotes');
    }
};
