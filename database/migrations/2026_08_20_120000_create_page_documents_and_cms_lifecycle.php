<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            // Created by 2026_08_15_000100_create_commerce_domain_tables.php.
            $table->dropUnique('pages_slug_unique');
            $table->string('slug')->nullable()->change();
            $table->string('title')->nullable()->change();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('page_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', ['draft', 'published']);
            $table->string('slug');
            $table->unsignedSmallInteger('schema_version');
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->char('content_hash', 64);
            $table->json('document');
            $table->timestamps();

            $table->unique(['page_id', 'stage']);
            $table->unique(['stage', 'slug']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('page_documents') && DB::table('page_documents')->exists()) {
            throw new RuntimeException(
                'Refusing to roll back CMS document storage while canonical documents exist. Export or migrate them first.',
            );
        }

        if (DB::table('pages')->whereNull('slug')->orWhereNull('title')->exists()) {
            throw new RuntimeException(
                'Refusing to restore non-null legacy page identity while pages without legacy slug or title exist.',
            );
        }

        Schema::dropIfExists('page_documents');

        Schema::table('pages', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->string('title')->nullable(false)->change();
            $table->dropColumn(['status', 'published_at', 'archived_at']);
            $table->unique('slug', 'pages_slug_unique');
        });
    }
};
