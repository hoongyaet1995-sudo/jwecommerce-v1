<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_hub_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('hub_url');
            $t->string('client_id');
            $t->string('key_id');
            $t->text('encrypted_secret');
            $t->string('registered_domain');
            $t->boolean('enabled')->default(false);
            $t->string('last_heartbeat_status')->nullable();
            $t->timestamp('last_heartbeat_at')->nullable();
            $t->timestamps();
        });
        Schema::create('social_hub_request_nonces', function (Blueprint $t) {
            $t->id();
            $t->string('client_id');
            $t->string('key_id');
            $t->string('nonce', 32);
            $t->timestamp('expires_at');
            $t->unique(['client_id', 'key_id', 'nonce'], 'social_hub_nonce_unique');
        });
        Schema::create('social_hub_test_event_inbox', function (Blueprint $t) {
            $t->id();
            $t->string('client_id');
            $t->string('event_id')->unique();
            $t->string('payload_hash', 64);
            $t->timestamp('received_at');
        });
    }

    public function down(): void
    {
        foreach (['social_hub_test_event_inbox', 'social_hub_request_nonces', 'social_hub_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
