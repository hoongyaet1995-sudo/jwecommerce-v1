<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('delivery_status', 16)->nullable();
            $table->string('provider_message_id', 512)->nullable();
            $table->string('send_error', 255)->nullable();
        });
        Schema::table('managed_social_accounts', function (Blueprint $table) {
            $table->string('messaging_error', 255)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn(['delivery_status', 'provider_message_id', 'send_error']));
        Schema::table('managed_social_accounts', fn (Blueprint $table) => $table->dropColumn('messaging_error'));
    }
};
