<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::table('marketing_content_templates',fn(Blueprint $t)=>$t->string('name',150)->change());}public function down():void {}};
