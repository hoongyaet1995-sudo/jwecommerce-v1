<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_library_assets', fn (Blueprint $t) => $t->string('disk', 12)->default('local'));
    }

    public function down(): void
    {
        Schema::table('marketing_library_assets', fn (Blueprint $t) => $t->dropColumn('disk'));
    }
};
