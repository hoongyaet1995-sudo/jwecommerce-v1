<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_pools', function (Blueprint $table) {
            $table->foreignId('marketplace_store_id')->nullable()->unique()
                ->constrained('marketplace_stores')->restrictOnDelete();
        });

        Schema::create('inventory_store_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_key', 80)->unique();
            $table->foreignId('marketplace_store_id')->nullable()->unique()
                ->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('management_status', 24)->default('not_managed');
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_store_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('configuration_version');
            $table->json('balance_versions');
            $table->json('payload');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('blockers')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_store_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('review_id')->unique();
            $table->string('operation_key', 120)->unique();
            $table->foreign('review_id')->references('id')->on('inventory_store_reviews')->restrictOnDelete();
            $table->string('kind', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('ledger_status', 24);
            $table->json('receipt');
            $table->timestamps();
        });

        Schema::create('inventory_store_sync_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id')->nullable();
            $table->foreign('operation_id')->references('id')->on('inventory_store_operations')->restrictOnDelete();
            $table->foreignId('marketplace_store_id')->nullable()->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('stage', 32);
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('target_quantity')->nullable();
            $table->unsignedBigInteger('balance_version');
            $table->string('event_key', 160)->unique();
            $table->json('provider_receipt')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
            $table->unique(['operation_id', 'marketplace_store_id', 'stage'], 'inventory_store_sync_stage_unique');
        });

        Schema::create('inventory_store_order_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 160)->unique();
            $table->string('order_line_key', 160);
            $table->string('channel_key', 80);
            $table->string('event', 32);
            $table->unsignedInteger('cumulative_quantity');
            $table->json('result');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_store_order_events');
        Schema::dropIfExists('inventory_store_sync_tasks');
        Schema::dropIfExists('inventory_store_operations');
        Schema::dropIfExists('inventory_store_reviews');
        Schema::dropIfExists('inventory_store_accounts');
        Schema::table('inventory_pools', fn (Blueprint $table) => $table->dropConstrainedForeignId('marketplace_store_id'));
    }
};
