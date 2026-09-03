<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('replacement_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('resolved_refund_id')->nullable()->constrained('order_refunds')->nullOnDelete();
            $table->string('status')->default('requested');
            $table->string('resolution');
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->timestamp('eligible_until');
            $table->unsignedInteger('received_quantity')->nullable();
            $table->string('condition')->nullable();
            $table->text('warehouse_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
        Schema::create('return_request_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->string('path'); $table->string('original_name'); $table->string('mime_type', 100); $table->unsignedBigInteger('size'); $table->timestamps();
        });
        Schema::create('return_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->text('note')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_events');
        Schema::dropIfExists('return_request_evidence');
        Schema::dropIfExists('return_requests');
    }
};
