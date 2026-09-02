<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_address_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_address_id')->constrained()->restrictOnDelete();
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->string('reason_code', 64);
            $table->text('internal_note');
            $table->boolean('address_book_sync_requested')->default(false);
            $table->string('address_book_sync_result', 40)->default('not_requested');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_address_amendments');
    }
};
