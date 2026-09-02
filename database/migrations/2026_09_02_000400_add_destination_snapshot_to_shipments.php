<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('shipments', fn (Blueprint $table) => $table->json('destination_snapshot')->nullable()->after('package_snapshot')); }
    public function down(): void { Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn('destination_snapshot')); }
};
