<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_hash', 64)->unique();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->unsignedBigInteger('staff_read_message_id')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->uuid('client_message_id');
            $table->string('sender_role', 20);
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->unique(['conversation_id', 'client_message_id']);
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('support_staff_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('support_staff_notes');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
