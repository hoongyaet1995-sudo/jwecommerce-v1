<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_research_budgets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('authorization_reference');
            $t->unsignedBigInteger('approved_microusd');
            $t->unsignedBigInteger('held_microusd')->default(0);
            $t->unsignedBigInteger('settled_microusd')->default(0);
            $t->string('currency', 3)->default('USD');
            $t->boolean('enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('marketing_research_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignId('actor_id')->constrained('users');
            $t->uuid('request_id');
            $t->string('input_hash', 64);
            $t->json('input_snapshot');
            $t->string('status', 20)->default('pending');
            foreach (['query_plan', 'sources', 'analysis', 'calls', 'reported_usage', 'pricing_snapshot'] as $column) {
                $t->json($column)->nullable();
            }
            $t->foreignUuid('budget_id')->constrained('marketing_research_budgets');
            $t->unsignedBigInteger('reserved_microusd');
            $t->unsignedBigInteger('reported_cost_microusd')->nullable();
            $t->string('error_code', 64)->nullable();
            $t->dateTime('started_at')->nullable();
            $t->dateTime('finished_at')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'request_id']);
            $t->index(['project_id', 'status', 'created_at']);
        });
        Schema::table('marketing_projects', function (Blueprint $t) {
            $t->uuid('active_research_run_id')->nullable();
            $t->uuid('current_research_run_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_projects', fn (Blueprint $t) => $t->dropColumn(['active_research_run_id', 'current_research_run_id']));
        Schema::dropIfExists('marketing_research_runs');
        Schema::dropIfExists('marketing_research_budgets');
    }
};
