<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_messages',function(Blueprint $table){
            $table->unsignedInteger('ai_settings_version')->nullable();
            $table->unsignedBigInteger('ai_control_version')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('support_messages',fn(Blueprint $table)=>$table->dropColumn(['ai_settings_version','ai_control_version']));
    }
};
