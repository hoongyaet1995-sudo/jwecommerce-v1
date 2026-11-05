<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('order_payments',function(Blueprint $t){$t->timestamp('confirmed_at')->nullable()->index();});
  Schema::table('order_refunds',function(Blueprint $t){$t->timestamp('completed_at')->nullable()->index();});
 }
 public function down(): void {
  Schema::table('order_payments',function(Blueprint $t){$t->dropColumn('confirmed_at');});
  Schema::table('order_refunds',function(Blueprint $t){$t->dropColumn('completed_at');});
 }
};
