<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('page_id_snapshot');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('coupon_id_snapshot');
            $table->string('coupon_code_snapshot', 120);
            $table->string('document_stage', 16);
            $table->string('section_id', 191);
            $table->string('section_type', 64);
            $table->string('email', 254)->unique();
            $table->timestamps();

            $table->index(['page_id', 'document_stage', 'section_id']);
            $table->index(['page_id_snapshot', 'document_stage', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriptions');
    }
};
