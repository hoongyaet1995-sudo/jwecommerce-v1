<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_publishing_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_key');
            $table->char('payload_hash',64);
            $table->json('payload');
            $table->string('status',30)->default('queued');
            $table->string('external_item_id')->nullable();
            $table->timestamps();
            $table->unique(['store_id','request_key'],'marketplace_publish_request_identity');
            $table->index(['store_id','product_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('marketplace_publishing_tasks'); }
};
