<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::table('marketplace_store_tokens',function(Blueprint $table){
  $table->string('credential_source',30)->default('oauth');
  $table->timestamp('test_lease_expires_at')->nullable();
 }); }
 public function down():void { Schema::table('marketplace_store_tokens',fn(Blueprint $table)=>$table->dropColumn(['credential_source','test_lease_expires_at'])); }
};
