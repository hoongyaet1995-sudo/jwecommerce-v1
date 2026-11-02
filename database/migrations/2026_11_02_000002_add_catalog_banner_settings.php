<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { foreach (['categories','collections'] as $table) { Schema::table($table, fn (Blueprint $t) => $t->json('image_settings')->nullable()); } }
 public function down(): void { foreach (['categories','collections'] as $table) { Schema::table($table, fn (Blueprint $t) => $t->dropColumn('image_settings')); } }
};
