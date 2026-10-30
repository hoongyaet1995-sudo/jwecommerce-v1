<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::table('marketing_email_send_reservations',function(Blueprint $t){$t->string('quota_key',64)->nullable();$t->index(['quota_key','created_at'],'marketing_email_mailbox_quota');});}public function down():void {Schema::table('marketing_email_send_reservations',function(Blueprint $t){$t->dropIndex('marketing_email_mailbox_quota');$t->dropColumn('quota_key');});}};
