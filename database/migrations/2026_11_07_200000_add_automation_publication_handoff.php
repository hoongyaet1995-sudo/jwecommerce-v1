<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('marketing_automation_occurrences',function(Blueprint $t){$t->string('publication_state')->nullable();$t->json('publication_dispatches')->nullable();$t->string('publication_reason')->nullable();});}
 public function down():void {Schema::table('marketing_automation_occurrences',fn(Blueprint $t)=>$t->dropColumn(['publication_state','publication_dispatches','publication_reason']));}
};
