<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('marketing_audience_export_members',function(Blueprint $t){$t->uuid('task_id');$t->uuid('contact_id');$t->primary(['task_id','contact_id']);});}
 public function down():void{Schema::dropIfExists('marketing_audience_export_members');}
};
