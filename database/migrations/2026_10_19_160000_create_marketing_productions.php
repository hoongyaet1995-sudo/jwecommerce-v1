<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_production_budgets', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->unsignedBigInteger('ceiling_microusd');
            $t->unsignedBigInteger('reserved_microusd');
            $t->text('note');
            $t->timestamps();
        });
        Schema::create('marketing_voice_assets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->string('signature', 64);
            $t->string('status', 24);
            $t->string('source', 32);
            $t->json('spec');
            $t->string('audio_path')->nullable();
            $t->string('alignment_path')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->decimal('duration_seconds', 10, 4)->nullable();
            $t->unsignedBigInteger('reserved_microusd')->default(0);
            $t->json('usage')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'signature']);
        });
        Schema::create('marketing_productions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignUuid('plan_id')->unique()->constrained('marketing_production_plans');
            $t->foreignId('owner_id')->constrained('users');
            $t->string('status', 24);
            $t->foreignUuid('voice_asset_id')->nullable()->constrained('marketing_voice_assets');
            $t->unsignedBigInteger('new_reserved_microusd')->default(0);
            $t->string('output_path')->nullable();
            $t->string('output_sha256', 64)->nullable();
            $t->json('result')->nullable();
            $t->string('error_code', 80)->nullable();
            $t->dateTime('started_at')->nullable();
            $t->dateTime('finished_at')->nullable();
            $t->timestamps();
            $t->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['marketing_productions', 'marketing_voice_assets', 'marketing_production_budgets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
