<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_transfers',function(Blueprint $table){
            $table->id();
            $table->string('operation_key',128)->unique();
            $table->char('request_hash',64);
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('destination_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->string('status',16)->default('draft');
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('notes',1000);
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_transfer_events',function(Blueprint $table){
            $table->id();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->restrictOnDelete();
            $table->string('operation_key',128);
            $table->char('request_hash',64);
            $table->string('action',16);
            $table->unsignedInteger('quantity');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('balance_id')->nullable();
            $table->unsignedInteger('physical_before')->nullable();
            $table->unsignedInteger('physical_after')->nullable();
            $table->json('receipt');
            $table->dateTime('created_at');
            $table->unique(['transfer_id','operation_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_events');
        Schema::dropIfExists('inventory_transfers');
    }
};
