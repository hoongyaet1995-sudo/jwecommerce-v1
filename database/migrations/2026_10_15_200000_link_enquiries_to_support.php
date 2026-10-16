<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {Schema::table('product_enquiries',function(Blueprint $t){$t->foreignUuid('support_conversation_id')->nullable()->unique()->constrained('support_conversations')->nullOnDelete();});}
    public function down():void {Schema::table('product_enquiries',function(Blueprint $t){$t->dropForeign(['support_conversation_id']);$t->dropUnique(['support_conversation_id']);$t->dropColumn('support_conversation_id');});}
};
