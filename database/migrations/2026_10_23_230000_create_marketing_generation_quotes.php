<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('marketing_generation_quotes',function(Blueprint $t){
            $t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');
            $t->unsignedInteger('story_version');$t->string('action',50);$t->json('payload');$t->string('source_digest',64);$t->string('pricing_digest',64);
            $t->unsignedBigInteger('cap_microusd');$t->unsignedInteger('max_posts');$t->json('pricing');$t->boolean('included')->default(false);
            $t->string('status',24)->default('quoted');$t->json('result')->nullable();$t->dateTime('confirmed_at')->nullable();$t->dateTime('expires_at');$t->timestamps();$t->index(['project_id','status']);
        });
        Schema::table('marketing_preparation_authorizations',function(Blueprint $t){$t->foreignUuid('billing_quote_id')->nullable()->constrained('marketing_generation_quotes');});
    }
    public function down():void {Schema::table('marketing_preparation_authorizations',fn(Blueprint $t)=>$t->dropConstrainedForeignId('billing_quote_id'));Schema::dropIfExists('marketing_generation_quotes');}
};
