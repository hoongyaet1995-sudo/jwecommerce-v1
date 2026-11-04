<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reward_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('reward_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('reward_wallets')->restrictOnDelete();
            $table->string('source')->unique();
            $table->bigInteger('coins');
            $table->bigInteger('remaining');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('order_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('reward_wallets')->restrictOnDelete();
            $table->json('snapshot');
            $table->json('redemption_lots');
            $table->bigInteger('credited')->default(0);
            $table->bigInteger('reversed')->default(0);
            $table->bigInteger('restored')->default(0);
            $table->timestamps();
        });
        Schema::create('reward_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('reward_wallets')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_key')->unique();
            $table->string('kind');
            $table->bigInteger('coins');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_entries');
        Schema::dropIfExists('order_rewards');
        Schema::dropIfExists('reward_lots');
        Schema::dropIfExists('reward_wallets');
    }
};
