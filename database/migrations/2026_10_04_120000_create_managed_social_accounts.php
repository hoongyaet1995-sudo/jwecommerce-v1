<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('managed_social_accounts',function(Blueprint $table){
            $table->id();
            $table->string('merchant_key',100);
            $table->string('platform',30);
            $table->string('app_id',100);
            $table->string('provider_account_id',100);
            $table->string('name');
            $table->text('access_token');
            $table->json('permissions');
            $table->json('tasks');
            $table->string('state',30)->default('connected');
            $table->unsignedBigInteger('authorized_by');
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_key','platform','provider_account_id'],'managed_social_account_owner_unique');
        });
    }
    public function down(): void {Schema::dropIfExists('managed_social_accounts');}
};
