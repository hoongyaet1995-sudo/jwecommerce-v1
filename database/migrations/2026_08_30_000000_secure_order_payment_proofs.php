<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->string('proof_disk', 32)->nullable()->after('proof_path');
            $table->string('proof_original_name')->nullable()->after('proof_disk');
            $table->string('proof_mime_type', 100)->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime_type');
            $table->string('proof_sha256', 64)->nullable()->after('proof_size');
            $table->string('proof_status', 32)->default('none')->after('proof_sha256');
            $table->timestamp('proof_reviewed_at')->nullable()->after('proof_uploaded_at');
        });

        DB::table('order_payments')
            ->whereNotNull('proof_path')
            ->update(['proof_disk' => 'public', 'proof_status' => 'uploaded']);

        Schema::create('order_payment_proof_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->text('reason')->nullable();
            $table->string('proof_sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['order_payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_proof_events');

        Schema::table('order_payments', function (Blueprint $table): void {
            $table->dropColumn([
                'proof_disk',
                'proof_original_name',
                'proof_mime_type',
                'proof_size',
                'proof_sha256',
                'proof_status',
                'proof_reviewed_at',
            ]);
        });
    }
};
