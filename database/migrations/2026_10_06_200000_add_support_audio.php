<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('support_attachments',function(Blueprint $t){$t->string('kind',16)->default('image');$t->decimal('duration_seconds',8,3)->nullable();});}
 public function down():void {Schema::table('support_attachments',fn(Blueprint $t)=>$t->dropColumn(['kind','duration_seconds']));}
};
