<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('replacement_shipment_id')->nullable()->constrained('shipments')->restrictOnDelete();
            $table->foreignId('active_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->string('reason_code', 64);
            $table->text('internal_note');
            $table->json('original_snapshot');
            $table->string('external_action_state', 40);
            $table->text('external_action_note')->nullable();
            $table->string('status', 40);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_corrections');
    }
};
