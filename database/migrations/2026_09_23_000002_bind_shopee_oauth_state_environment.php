<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_oauth_states', function (Blueprint $table) {
            $table->string('environment', 20)->nullable();
            $table->string('application_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_oauth_states', function (Blueprint $table) {
            $table->dropColumn(['environment', 'application_id']);
        });
    }
};
