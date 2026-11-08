<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_media_reuses', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('owner_id')->constrained('users');
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignUuid('library_asset_id')->constrained('marketing_library_assets');
            $t->foreignUuid('project_asset_id')->unique()->constrained('marketing_assets');
            $t->char('source_sha256', 64);
            $t->timestamps();
            $t->unique(['project_id', 'library_asset_id'], 'marketing_project_library_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_media_reuses');
    }
};
