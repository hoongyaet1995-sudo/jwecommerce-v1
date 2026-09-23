<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_hub_event_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('hub_event_id', 100)->unique();
            $table->foreignId('store_id')->constrained('marketplace_stores');
            $table->string('event_type', 40);
            $table->string('external_order_id', 100);
            $table->string('status', 24)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_hub_event_inbox');
    }
};
