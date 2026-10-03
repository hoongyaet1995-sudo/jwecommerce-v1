<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connection_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('ai_connection_settings')->insert(['id' => 1]);
        Schema::create('ai_connections', function (Blueprint $table) {
            $table->string('provider', 40)->primary();
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('support_enabled')->default(false);
            $table->boolean('builder_enabled')->default(false);
            $table->string('text_model')->nullable();
            $table->string('image_model')->nullable();
            $table->json('models')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('text_tested_at')->nullable();
            $table->string('last_error', 100)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connections');
        Schema::dropIfExists('ai_connection_settings');
    }
};
