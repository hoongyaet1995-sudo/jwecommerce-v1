<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('disk', 64);
            $table->string('path')->unique();
            $table->string('url')->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64);
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('orphaned_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cms_media_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('asset_id');
            $table->foreignId('page_document_id');
            $table->string('field_path', 512);
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('cms_media_assets')->cascadeOnDelete();
            $table->foreign('page_document_id')->references('id')->on('page_documents')->cascadeOnDelete();
            $table->unique(['page_document_id', 'field_path'], 'cms_media_references_document_path_unique');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable('cms_media_references') && DB::table('cms_media_references')->exists())
            || (Schema::hasTable('cms_media_assets') && DB::table('cms_media_assets')->exists())) {
            throw new RuntimeException(
                'Refusing to roll back CMS managed media storage while assets or references exist.',
            );
        }

        Schema::dropIfExists('cms_media_references');
        Schema::dropIfExists('cms_media_assets');
    }
};
