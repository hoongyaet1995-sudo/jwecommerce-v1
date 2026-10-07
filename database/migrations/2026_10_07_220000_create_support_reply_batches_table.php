<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('support_reply_batches',function(Blueprint $t){
            $t->uuid('id')->primary();$t->uuid('conversation_id');
            $t->unsignedBigInteger('first_source_id');$t->unsignedBigInteger('latest_source_id');
            $t->string('language',12)->nullable();$t->string('status',24)->default('collecting');
            $t->unsignedBigInteger('settings_version');$t->unsignedBigInteger('control_version');
            $t->dateTime('due_at');$t->dateTime('deadline_at');$t->dateTime('generation_started_at')->nullable();
            $t->longText('segments')->nullable();$t->longText('actions')->nullable();$t->json('trace')->nullable();
            $t->unsignedTinyInteger('next_index')->default(0);$t->unsignedTinyInteger('interval_seconds')->default(1);
            $t->string('stop_reason',80)->nullable();$t->timestamps();
            $t->index(['conversation_id','status']);$t->index(['status','due_at']);
            $t->unique(['conversation_id','latest_source_id']);
        });
    }
    public function down():void {Schema::dropIfExists('support_reply_batches');}
};
