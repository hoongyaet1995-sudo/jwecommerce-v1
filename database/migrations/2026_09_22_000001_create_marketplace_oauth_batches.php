<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_oauth_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('provider', 30);
            $table->string('environment', 30);
            $table->string('application_id', 100);
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedBigInteger('reconnect_store_id')->nullable();
            $table->string('main_account_id', 100);
            $table->text('seed_access_token')->nullable();
            $table->text('seed_refresh_token')->nullable();
            $table->timestamp('seed_access_expires_at')->nullable();
            $table->timestamp('seed_refresh_expires_at')->nullable();
            $table->json('merchant_id_list')->nullable();
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('connected_count')->default(0);
            $table->unsignedInteger('reauthorized_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['actor_id','status']);
        });
        Schema::create('marketplace_oauth_batch_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('marketplace_oauth_batches')->cascadeOnDelete();
            $table->string('external_shop_id', 100);
            $table->unsignedBigInteger('marketplace_store_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('result_type', 30)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id','external_shop_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('marketplace_oauth_batch_stores');
        Schema::dropIfExists('marketplace_oauth_batches');
    }
};
