<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_projects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('owner_id')->constrained('users');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('source_type', 16);
            $t->string('name');
            $t->json('snapshot');
            $t->json('brief');
            $t->json('concepts')->nullable();
            $t->unsignedTinyInteger('selected_concept')->nullable();
            $t->string('stage', 24)->default('source');
            $t->string('status', 32)->default('needs_confirmation');
            $t->unsignedInteger('version')->default(1);
            $t->uuid('current_revision_id')->nullable();
            $t->uuid('confirmed_revision_id')->nullable();
            $t->uuid('busy_run_id')->nullable();
            $t->string('last_error', 160)->nullable();
            $t->timestamp('source_confirmed_at')->nullable();
            $t->timestamps();
            $t->index(['owner_id', 'updated_at']);
            $t->index(['owner_id', 'status']);
        });
        Schema::create('marketing_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignId('actor_id')->constrained('users');
            $t->unsignedInteger('number');
            $t->json('document');
            $t->json('context');
            $t->text('feedback')->nullable();
            $t->uuid('parent_id')->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('users');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'number']);
        });
        Schema::create('marketing_assets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->string('name');
            $t->string('path');
            $t->string('mime', 80);
            $t->unsignedBigInteger('size');
            $t->boolean('authorized')->default(false);
            $t->timestamps();
        });
        Schema::create('marketing_ai_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->uuid('request_id');
            $t->string('action', 20);
            $t->string('status', 20);
            $t->text('feedback')->nullable();
            $t->string('error', 160)->nullable();
            $t->decimal('reserved_usd', 8, 4)->default(0);
            $t->json('usage')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'request_id']);
        });
    }

    public function down(): void
    {
        foreach (['marketing_ai_runs', 'marketing_assets', 'marketing_revisions', 'marketing_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
