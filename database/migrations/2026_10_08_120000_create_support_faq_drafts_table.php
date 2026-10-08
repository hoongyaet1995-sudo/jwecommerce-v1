<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_faq_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_message_id')->nullable()->unique()->constrained('support_messages')->nullOnDelete();
            $t->foreignUuid('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
            $t->foreignId('staff_message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $t->text('question');
            $t->text('answer');
            $t->string('category', 100)->default('others');
            $t->string('channel', 20);
            $t->string('status', 20)->default('Needs review')->index();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('published_faq_id', 100)->nullable()->unique();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
        DB::table('store_settings')->insertOrIgnore(['key' => 'support_improvement', 'value' => json_encode(['activatedAt' => now()->toIso8601String()]), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('support_faq_drafts');
    }
};
