<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('customers',fn(Blueprint $t)=>$t->date('date_of_birth')->nullable());
  Schema::table('marketing_audience_contacts',function(Blueprint $t){$t->unsignedTinyInteger('birthday_month')->nullable();$t->index(['merchant_key','birthday_month'],'audience_merchant_birthday_month');});
 }
 public function down():void {
  Schema::table('marketing_audience_contacts',function(Blueprint $t){$t->dropIndex('audience_merchant_birthday_month');$t->dropColumn('birthday_month');});
  Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn('date_of_birth'));
 }
};
