<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_library_assets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('owner_id')->constrained('users');
            $t->uuid('project_id')->nullable()->index();
            $t->uuid('library_id')->nullable()->index();
            $t->uuid('avatar_id')->nullable()->index();
            $t->string('identity', 64)->unique();
            $t->string('source_type', 50);
            $t->string('source_id', 64);
            $t->string('slot', 180);
            $t->string('title');
            $t->string('project_name')->nullable();
            $t->string('path', 1024);
            $t->string('sha256', 64);
            $t->string('mime', 80);
            $t->string('kind', 8);
            $t->string('origin', 12);
            $t->string('stage', 40);
            $t->string('selection', 20)->default('not-reviewed')->index();
            $t->unsignedBigInteger('bytes');
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->decimal('seconds', 10, 3)->nullable();
            $t->timestamp('occurred_at')->nullable();
            $t->timestamps();
            $t->index(['owner_id', 'kind', 'occurred_at'], 'mkt_library_owner_kind');
            $t->index(['owner_id', 'origin', 'occurred_at'], 'mkt_library_owner_origin');
            $t->index(['source_type', 'source_id'], 'mkt_library_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_library_assets');
    }
};
