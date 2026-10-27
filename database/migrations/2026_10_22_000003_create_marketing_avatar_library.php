<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_avatar_libraries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('owner_id')->unique()->constrained('users');
            $t->string('name', 80);
            $t->timestamps();
        });
        Schema::create('marketing_avatar_library_members', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('library_id')->constrained('marketing_avatar_libraries');
            $t->foreignId('user_id')->constrained('users');
            $t->string('role', 20);
            $t->unique(['library_id', 'user_id'], 'avatar_members_unique');
            $t->timestamps();
        });
        Schema::create('marketing_avatars', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('library_id')->constrained('marketing_avatar_libraries');
            $t->uuid('request_id');
            $t->string('request_digest', 64);
            $t->string('name', 80);
            $t->string('kind', 20);
            $t->string('status', 20)->default('draft');
            $t->json('draft');
            $t->unsignedInteger('current_version')->default(0);
            $t->timestamps();
            $t->unique(['library_id', 'request_id'], 'avatar_draft_request');
        });
        Schema::create('marketing_avatar_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('avatar_id')->constrained('marketing_avatars');
            $t->unsignedInteger('version');
            $t->uuid('request_id');
            $t->string('request_digest', 64);
            $t->string('name', 80);
            $t->string('kind', 20);
            $t->string('look', 300);
            $t->string('identity_digest', 64);
            $t->json('references');
            $t->json('rights');
            $t->timestamps();
            $t->unique(['avatar_id', 'version'], 'avatar_version_number');
            $t->unique(['avatar_id', 'request_id'], 'avatar_publish_request');
        });
        Schema::table('marketing_storyboard_presenters', fn (Blueprint $t) => $t->json('avatar_binding')->nullable());
    }

    public function down(): void
    {
        Schema::table('marketing_storyboard_presenters', fn (Blueprint $t) => $t->dropColumn('avatar_binding'));
        Schema::dropIfExists('marketing_avatar_versions');
        Schema::dropIfExists('marketing_avatars');
        Schema::dropIfExists('marketing_avatar_library_members');
        Schema::dropIfExists('marketing_avatar_libraries');
    }
};
