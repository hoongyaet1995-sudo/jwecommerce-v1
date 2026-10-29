<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {if(Schema::getColumnType('marketing_content_templates','account_id')==='string')Schema::table('marketing_content_templates',fn(Blueprint $t)=>$t->unsignedBigInteger('account_id')->nullable()->change());}
 public function down():void {}
};
