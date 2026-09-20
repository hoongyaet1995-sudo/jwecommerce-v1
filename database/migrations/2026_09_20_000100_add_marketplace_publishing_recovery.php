<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_publishing_tasks', function (Blueprint $table) {
            $table->string('stage', 40)->nullable()->after('status');
            $table->string('error_code', 80)->nullable()->after('external_item_id');
            $table->text('safe_error_message')->nullable()->after('error_code');
            $table->json('safe_diagnostics')->nullable()->after('safe_error_message');
            $table->timestamp('failed_at')->nullable()->after('safe_diagnostics');
            $table->foreignId('previous_task_id')->nullable()->after('failed_at');
            $table->foreign('previous_task_id','mp_task_previous_fk')->references('id')->on('marketplace_publishing_tasks')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1)->after('previous_task_id');
        });
        Schema::create('marketplace_publishing_recovery_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('task_id');
            $table->foreignId('actor_id');
            $table->foreignId('store_id');
            $table->foreignId('product_id');
            $table->text('reason');
            $table->string('confirmation_version', 30);
            $table->char('blocker_hash', 64);
            $table->timestamp('expires_at');
            $table->foreignId('consumed_task_id')->nullable();
            $table->timestamps();
            $table->index(['task_id','actor_id','expires_at'],'marketplace_publish_recovery_lookup');
            $table->foreign('task_id','mp_recovery_task_fk')->references('id')->on('marketplace_publishing_tasks')->restrictOnDelete();
            $table->foreign('actor_id','mp_recovery_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('store_id','mp_recovery_store_fk')->references('id')->on('marketplace_stores')->restrictOnDelete();
            $table->foreign('product_id','mp_recovery_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('consumed_task_id','mp_recovery_consumed_fk')->references('id')->on('marketplace_publishing_tasks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_publishing_recovery_authorizations');
        Schema::table('marketplace_publishing_tasks', function (Blueprint $table) {
            $table->dropForeign('mp_task_previous_fk');
            $table->dropColumn('previous_task_id');
            $table->dropColumn(['stage','error_code','safe_error_message','safe_diagnostics','failed_at','revision']);
        });
    }
};
