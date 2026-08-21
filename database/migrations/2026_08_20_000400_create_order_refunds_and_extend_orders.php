<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('customer_note')->nullable()->after('tracking_number');
            $table->text('internal_note')->nullable()->after('customer_note');
        });

        Schema::create('order_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('reason');
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['customer_note', 'internal_note']);
        });
    }
};
