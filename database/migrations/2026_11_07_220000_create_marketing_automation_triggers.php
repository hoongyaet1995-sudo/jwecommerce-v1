<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('marketing_automation_triggers',function(Blueprint $t){$t->uuid('id')->primary();$t->string('client_id',100);$t->string('key_id',100);$t->string('registered_domain');$t->string('payload_hash',64);$t->string('status');$t->json('result')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('finished_at')->nullable();$t->timestamps();$t->index(['status','created_at']);});}
 public function down():void {Schema::dropIfExists('marketing_automation_triggers');}
};
