<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_publication_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $t->unsignedInteger('lock_version');
            $t->string('content_hash', 64);
            $t->unsignedBigInteger('actor_id');
            $t->json('checks');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_publication_reviews');
    }
};
