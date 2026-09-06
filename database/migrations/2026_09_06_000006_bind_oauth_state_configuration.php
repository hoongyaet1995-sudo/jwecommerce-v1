<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_oauth_states', function (Blueprint $table) {
            // Existing unbound attempts deliberately cannot pass the new comparison.
            $table->char('configuration_hash', 64)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('marketplace_oauth_states', fn (Blueprint $table) => $table->dropColumn('configuration_hash'));
    }
};
