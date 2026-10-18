<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('draft_version');
            $t->unsignedSmallInteger('schema_version');
            $t->char('content_hash', 64);
            $t->json('document');
            $t->string('kind', 24);
            $t->string('name', 100)->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_name')->nullable();
            $t->unsignedBigInteger('restored_from')->nullable();
            $t->dateTime('created_at');
            $t->index(['page_id', 'id']);
        });
        Schema::create('page_revision_media', function (Blueprint $t) {
            $t->foreignId('revision_id')->constrained('page_revisions')->cascadeOnDelete();
            $t->uuid('asset_id');
            $t->foreign('asset_id')->references('id')->on('cms_media_assets')->restrictOnDelete();
            $t->primary(['revision_id', 'asset_id']);
            $t->index('asset_id');
        });
        DB::table('page_documents')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $d) {
                $id = DB::table('page_revisions')->insertGetId(['page_id' => $d->page_id, 'draft_version' => $d->lock_version, 'schema_version' => $d->schema_version, 'content_hash' => $d->content_hash, 'document' => $d->document, 'kind' => $d->stage === 'published' ? 'published' : 'baseline', 'actor_name' => 'Existing page', 'created_at' => now()]);
                $assets = DB::table('cms_media_references')->where('page_document_id', $d->id)->distinct()->pluck('asset_id');
                foreach ($assets as $asset) {
                    DB::table('page_revision_media')->insert(['revision_id' => $id, 'asset_id' => $asset]);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::table('page_revisions')->exists()) {
            throw new RuntimeException('Export page history before rolling back this migration.');
        }Schema::dropIfExists('page_revision_media');
        Schema::dropIfExists('page_revisions');
    }
};
