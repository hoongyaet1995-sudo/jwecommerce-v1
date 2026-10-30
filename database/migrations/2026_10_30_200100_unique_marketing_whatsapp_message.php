<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('marketing_whatsapp_deliveries',fn(Blueprint $t)=>$t->unique(['account_id','provider_message_id'],'marketing_wa_provider_once'));}
 public function down():void{Schema::table('marketing_whatsapp_deliveries',fn(Blueprint $t)=>$t->dropUnique('marketing_wa_provider_once'));}
};
