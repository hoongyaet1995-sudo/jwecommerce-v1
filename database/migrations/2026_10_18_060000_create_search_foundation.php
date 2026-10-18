<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_connections', fn (Blueprint $t) => $t->boolean('search_enabled')->default(false));
        Schema::table('collections', function (Blueprint $t) {
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
        });
        Schema::create('search_entries', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20);
            $t->unsignedBigInteger('entity_id');
            $t->string('stage', 12)->default('live');
            $t->string('source', 20)->default('system');
            $t->string('source_hash', 64)->nullable();
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->json('topics')->nullable();
            $t->boolean('title_locked')->default(false);
            $t->boolean('description_locked')->default(false);
            $t->boolean('excluded')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['kind', 'entity_id', 'stage']);
        });
        Schema::create('search_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20);
            $t->unsignedBigInteger('entity_id');
            $t->string('stage', 12);
            $t->string('source_hash', 64);
            $t->json('facts');
            $t->json('before');
            $t->json('result')->nullable();
            $t->string('status', 20)->default('queued');
            $t->string('model')->nullable();
            $t->json('usage')->nullable();
            $t->string('error', 80)->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedInteger('entry_version')->default(0);
            $t->boolean('replace_legacy')->default(false);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->timestamp('available_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'available_at']);
            $t->index(['kind', 'entity_id', 'source_hash']);
        });
        Schema::create('search_redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path')->unique();
            $t->string('kind', 20);
            $t->unsignedBigInteger('entity_id');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_redirects');
        Schema::dropIfExists('search_tasks');
        Schema::dropIfExists('search_entries');
        Schema::table('collections', fn (Blueprint $t) => $t->dropColumn(['seo_title', 'seo_description']));
        Schema::table('ai_connections', fn (Blueprint $t) => $t->dropColumn('search_enabled'));
    }
};
