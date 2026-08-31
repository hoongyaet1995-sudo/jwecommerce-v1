<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('status_name', 120);
            $table->string('color', 32);
            $table->boolean('email_enabled')->default(false);
            $table->string('email_subject', 255)->default('');
            $table->string('email_preheader', 255)->default('');
            $table->string('email_heading', 255)->default('');
            $table->text('email_body');
            $table->string('email_cta_text', 120)->default('');
            $table->string('email_cta_url', 2048)->default('');
            $table->boolean('whatsapp_enabled')->default(false);
            $table->text('whatsapp_message');
            $table->json('whatsapp_cta_buttons')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
