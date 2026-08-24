<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_media_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('asset_id');
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->string('field_path', 512);
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('cms_media_assets')->cascadeOnDelete();
            $table->unique(['owner_type', 'owner_id', 'field_path'], 'catalog_media_reference_owner_field_unique');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_media_references');
    }
};
