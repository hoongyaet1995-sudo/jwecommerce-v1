<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('avatar', 2048)->nullable();
            $table->string('status', 32)->default('active');
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
        });
        Schema::table('customer_addresses', fn (Blueprint $table) => $table->string('type', 32)->default('shipping'));
    }

    public function down(): void
    {
        Schema::table('customer_addresses', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['avatar', 'status', 'tags', 'notes']));
    }
};
