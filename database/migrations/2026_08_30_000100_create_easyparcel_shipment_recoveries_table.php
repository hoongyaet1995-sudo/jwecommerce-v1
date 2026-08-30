<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easyparcel_shipment_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->string('state', 32);
            $table->text('reason');
            $table->string('provider_cancel_state', 24);
            $table->string('provider_cancel_message', 500)->nullable();
            $table->foreignId('replacement_shipment_id')
                ->nullable()
                ->unique()
                ->constrained('shipments')
                ->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'state']);
            $table->index(['provider_cancel_state', 'created_at'], 'ep_recoveries_cancel_state_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easyparcel_shipment_recoveries');
    }
};
