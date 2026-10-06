<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('managed_social_accounts',function(Blueprint $table){
            $table->string('waba_id',100)->nullable();
            $table->string('display_phone_number',100)->nullable();
            $table->string('connection_method',40)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('managed_social_accounts',fn(Blueprint $table)=>$table->dropColumn(['waba_id','display_phone_number','connection_method']));
    }
};
