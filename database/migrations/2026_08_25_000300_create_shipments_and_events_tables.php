<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('source', 24)->default('manual');
            $table->string('carrier_code', 80)->nullable();
            $table->string('carrier_name', 120);
            $table->string('service_code', 120)->nullable();
            $table->string('service_name', 160)->nullable();
            $table->string('tracking_number', 160)->nullable()->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('shipping_cost')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->string('label_path')->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->timestamp('occurred_at')->index();
            $table->string('location', 255)->nullable();
            $table->text('description');
            $table->string('source', 24)->default('manual');
            $table->string('external_event_id', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shipment_id', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
    }
};
