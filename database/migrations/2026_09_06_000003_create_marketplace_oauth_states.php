<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_oauth_states', function (Blueprint $table) {
            $table->id();
            $table->char('state_hash', 64)->unique();
            $table->unsignedBigInteger('actor_id');
            $table->string('platform', 20);
            $table->char('deployment_hash', 64);
            $table->foreignId('reconnect_store_id')->nullable()->constrained('marketplace_stores')->restrictOnDelete();
            $table->dateTime('expires_at')->index();
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_oauth_states');
    }
};
