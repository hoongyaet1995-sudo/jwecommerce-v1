<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_storyboard_presenters', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignId('owner_id')->constrained('users');
            $t->uuid('request_id');
            $t->string('request_digest', 64);
            $t->string('name', 80);
            $t->string('kind', 20);
            $t->unsignedInteger('version')->default(1);
            $t->foreignUuid('asset_id')->constrained('marketing_assets');
            $t->json('reference');
            $t->timestamps();
            $t->unique(['project_id', 'request_id'], 'sb_presenter_request_idx');
        });
        Schema::create('marketing_storyboard_provider_calls', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->uuid('authorization_id');
            $t->string('role', 20);
            $t->string('grant_reference', 180);
            $t->unsignedBigInteger('cap_microusd');
            $t->unsignedBigInteger('actual_microusd')->nullable();
            $t->string('status', 20);
            $t->json('input');
            $t->json('result')->nullable();
            $t->timestamps();
            $t->index(['project_id', 'grant_reference'], 'sb_provider_grant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_storyboard_provider_calls');
        Schema::dropIfExists('marketing_storyboard_presenters');
    }
};
