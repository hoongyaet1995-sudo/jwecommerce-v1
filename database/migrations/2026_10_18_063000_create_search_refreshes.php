<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_refreshes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->boolean('use_ai');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->timestamps();
        });
        Schema::table('search_tasks', fn (Blueprint $table) => $table->unsignedBigInteger('refresh_id')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('search_tasks', function (Blueprint $table) {
            $table->dropIndex(['refresh_id']);
            $table->dropColumn('refresh_id');
        });
        Schema::dropIfExists('search_refreshes');
    }
};
