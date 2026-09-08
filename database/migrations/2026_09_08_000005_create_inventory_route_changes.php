<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_route_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('inventory_fulfillment_routes')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('before')->nullable();$table->json('after');$table->dateTime('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('inventory_route_changes'); }
};
