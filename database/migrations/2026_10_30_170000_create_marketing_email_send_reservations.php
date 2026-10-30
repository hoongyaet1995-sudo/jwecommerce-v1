<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::create('marketing_email_send_reservations',function(Blueprint $t){$t->uuid('delivery_id')->primary();$t->string('merchant_key',100);$t->dateTime('created_at');$t->index(['merchant_key','created_at']);});}public function down():void {Schema::dropIfExists('marketing_email_send_reservations');}};
