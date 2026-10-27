<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {Schema::create('marketing_production_steps',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('production_id')->constrained('marketing_productions');$t->string('step_key',40);$t->string('kind',24);$t->string('input_signature',64);$t->json('depends_on');$t->string('status',24);$t->uuid('claim_token')->nullable();$t->unsignedInteger('attempts')->default(0);$t->string('provider_task_id')->nullable();$t->json('result')->nullable();$t->string('output_sha256',64)->nullable();$t->string('error_code')->nullable();$t->timestamps();$t->unique(['production_id','step_key']);});}
    public function down():void {Schema::dropIfExists('marketing_production_steps');}
};
