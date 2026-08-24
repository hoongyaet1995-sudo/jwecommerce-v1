<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 16)->index();
            $table->string('active_lock', 16)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 255);
            $table->text('comment');
            $table->string('order_number_snapshot');
            $table->string('product_title_snapshot');
            $table->string('variant_title_snapshot')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['order_item_id', 'attempt_number']);
            $table->unique(['order_item_id', 'active_lock']);
        });

        Schema::create('product_review_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->string('original_path')->unique();
            $table->string('public_path')->nullable()->unique();
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('byte_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('checksum_sha256', 64)->index();
            $table->unsignedTinyInteger('position');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['product_review_id', 'position']);
        });

        Schema::create('product_review_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48)->index();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->uuid('batch_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('product_review_events');
        Schema::dropIfExists('product_review_media');
        Schema::dropIfExists('product_reviews');
    }
};
