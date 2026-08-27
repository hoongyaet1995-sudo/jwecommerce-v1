<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('environment');
            $table->boolean('enabled')->default(false);
            $table->string('connection_state')->default('unverified');
            $table->json('public_metadata')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'environment']);
        });

        Schema::create('shipping_provider_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('environment');
            $table->text('credentials');
            $table->timestamps();
            $table->unique(['provider', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_provider_credentials');
        Schema::dropIfExists('shipping_providers');
    }
};
