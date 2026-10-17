<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_enquiry_intakes', function (Blueprint $t) {
            $t->id();
            $t->uuid('conversation_id')->unique();
            $t->foreign('conversation_id')->references('id')->on('support_conversations')->cascadeOnDelete();
            $t->json('fields')->nullable();
            $t->json('unresolved')->nullable();
            $t->json('answered')->nullable();
            $t->boolean('ai_suspended')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_enquiry_intakes');
    }
};
