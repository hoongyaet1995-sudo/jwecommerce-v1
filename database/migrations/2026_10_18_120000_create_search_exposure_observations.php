<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_exposure_observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('fingerprint', 64)->unique();
            $table->string('platform', 30);
            $table->string('status', 20);
            $table->timestamp('observed_at');
            $table->json('payload');
            $table->timestamps();
            $table->index(['platform', 'status', 'observed_at'], 'search_exposure_platform_status_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_exposure_observations');
    }
};
