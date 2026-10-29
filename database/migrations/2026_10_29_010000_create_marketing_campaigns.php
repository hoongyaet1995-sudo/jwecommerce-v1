<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('marketing_campaigns',function(Blueprint $t){$t->uuid('id')->primary();$t->string('merchant_key',100);$t->foreignId('user_id')->constrained();$t->string('name',120);$t->string('objective',60);$t->string('status',20)->default('Draft');$t->date('starts_on');$t->date('ends_on');$t->string('timezone',80);$t->text('destination_url');$t->string('cta',40);$t->unsignedInteger('version')->default(1);$t->timestamps();$t->index(['merchant_key','user_id','updated_at']);});Schema::table('marketing_posts',function(Blueprint $t){$t->foreignUuid('campaign_id')->nullable()->constrained('marketing_campaigns')->restrictOnDelete();});}
 public function down():void{Schema::table('marketing_posts',function(Blueprint $t){$t->dropForeign(['campaign_id']);$t->dropColumn('campaign_id');});Schema::dropIfExists('marketing_campaigns');}
};
