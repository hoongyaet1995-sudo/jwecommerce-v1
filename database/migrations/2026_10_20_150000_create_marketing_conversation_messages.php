<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_conversation_messages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects')->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users');
            $t->uuid('request_id');
            $t->unsignedBigInteger('sequence');
            $t->string('role', 12);
            $t->string('screen', 20);
            $t->text('content');
            $t->string('status', 16)->default('completed');
            $t->unsignedInteger('base_version');
            $t->json('proposal')->nullable();
            $t->string('proposal_status', 16)->nullable();
            $t->uuid('run_id')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'request_id', 'role'], 'marketing_chat_request_role');
            $t->unique(['project_id', 'sequence'], 'marketing_chat_sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_conversation_messages');
    }
};
