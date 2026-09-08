<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('state')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->json('supported_carriers')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
        // Accounting owner: shared pool OR physical warehouse. Stores never own balances.
        // No pool is created or activated by this migration.
        Schema::create('inventory_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_key', 80)->unique();
            $table->string('kind', 16);
            $table->foreignId('warehouse_id')->nullable()->unique()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_pool_id')->constrained('inventory_pools')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            // Null means not established, including reservation coverage and safety policy.
            $table->unsignedInteger('physical_stock')->nullable();
            $table->unsignedInteger('reserved_stock')->nullable();
            $table->unsignedInteger('safety_stock')->nullable();
            $table->string('bin_location')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('obligations_reconciled_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['inventory_pool_id', 'product_variant_id'], 'inventory_balance_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_pools');
        Schema::dropIfExists('inventory_warehouses');
    }
};
