<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_storyboard_workspaces')) {
            Schema::create('marketing_storyboard_workspaces', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('project_id')->unique()->constrained('marketing_projects');
                $t->unsignedInteger('version')->default(1);
                $t->uuid('source_revision_id');
                $t->json('scene_order');
                $t->json('profile');
                $t->json('last_confirmation')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_scenes')) {
            Schema::create('marketing_scenes', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('project_id')->constrained('marketing_projects');
                $t->uuid('current_revision_id');
                $t->uuid('replaced_from_id')->nullable();
                $t->timestamp('removed_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_scene_revisions')) {
            Schema::create('marketing_scene_revisions', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('scene_id')->constrained('marketing_scenes');
                $t->uuid('parent_id')->nullable();
                $t->json('content');
                $t->string('signature', 64);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_storyboard_commands')) {
            Schema::create('marketing_storyboard_commands', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('project_id')->constrained('marketing_projects');
                $t->uuid('request_id');
                $t->string('digest', 64);
                $t->json('result');
                $t->timestamps();
                $t->unique(['project_id', 'request_id'], 'sb_command_request_unique');
            });
        }
        if (! Schema::hasTable('marketing_preparation_authorizations')) {
            Schema::create('marketing_preparation_authorizations', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('project_id')->constrained('marketing_projects');
                $t->uuid('request_id');
                $t->unsignedInteger('story_version');
                $t->json('scope');
                foreach (['cap', 'reserved', 'spent', 'unknown'] as $k) {
                    $t->unsignedBigInteger($k.'_microusd')->default(0);
                }$t->json('claims');
                $t->string('status', 20);
                $t->timestamp('expires_at');
                $t->timestamps();
                $t->unique(['project_id', 'request_id'], 'sb_authorization_request_unique');
            });
        }
        if (! Schema::hasTable('marketing_storyboard_semantic_runs')) {
            Schema::create('marketing_storyboard_semantic_runs', function (Blueprint $t) {
                $t->string('id', 64)->primary();
                $t->string('status', 20);
                $t->json('result')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_storyboard_tasks')) {
            Schema::create('marketing_storyboard_tasks', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('project_id')->constrained('marketing_projects');
                $t->uuid('authorization_id');
                $t->uuid('revision_id');
                $t->string('signature', 64);
                $t->string('status', 20);
                $t->json('result')->nullable();
                $t->timestamps();
                $t->unique(['authorization_id', 'revision_id'], 'sb_task_revision_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['marketing_storyboard_semantic_runs', 'marketing_storyboard_tasks', 'marketing_preparation_authorizations', 'marketing_storyboard_commands', 'marketing_scene_revisions', 'marketing_scenes', 'marketing_storyboard_workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
