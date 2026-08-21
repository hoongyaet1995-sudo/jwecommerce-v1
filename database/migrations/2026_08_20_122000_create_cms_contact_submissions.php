<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('page_id_snapshot');
            $table->string('document_stage', 16);
            $table->string('section_id', 191);
            $table->string('section_type', 64);
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('subject', 200);
            $table->string('category', 120);
            $table->text('message');
            $table->timestamps();

            $table->index(['page_id', 'document_stage', 'section_id']);
            $table->index(['page_id_snapshot', 'document_stage', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_contact_submissions');
    }
};
