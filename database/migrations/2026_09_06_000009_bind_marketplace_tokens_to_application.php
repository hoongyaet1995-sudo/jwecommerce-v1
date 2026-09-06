<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_store_tokens', function (Blueprint $table) {
            // Legacy rows remain unbound and require reauthorization; never infer their application.
            $table->string('application_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_store_tokens', fn (Blueprint $table) => $table->dropColumn('application_id'));
    }
};
