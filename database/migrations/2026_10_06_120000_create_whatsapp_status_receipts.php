<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('whatsapp_status_receipts',function(Blueprint $t){$t->id();$t->char('event_key',64)->unique();$t->unsignedBigInteger('account_id')->index();$t->string('provider_message_id',512);$t->string('recipient_id',100);$t->string('status',20);$t->timestamps();$t->index(['account_id','provider_message_id']);});}
 public function down():void {Schema::dropIfExists('whatsapp_status_receipts');}
};
