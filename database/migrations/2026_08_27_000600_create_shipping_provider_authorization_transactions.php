<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_provider_authorization_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_provider_id');
            $table->foreignId('user_id');
            $table->char('state_hash', 64)->unique();
            $table->char('session_fingerprint', 64);
            $table->unsignedBigInteger('configuration_version');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index([
                'shipping_provider_id',
                'user_id',
                'session_fingerprint',
                'consumed_at',
            ], 'shipping_provider_oauth_pending_index');
            $table->foreign('shipping_provider_id', 'shipping_provider_oauth_provider_foreign')
                ->references('id')
                ->on('shipping_providers')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'shipping_provider_oauth_user_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_provider_authorization_transactions');
    }
};
