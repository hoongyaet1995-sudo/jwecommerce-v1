<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enquiry_quotations')) {
            Schema::create('enquiry_quotations', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('product_enquiry_id')->unique()->constrained('product_enquiries')->restrictOnDelete();
                $t->string('number', 40)->unique();
                $t->string('status', 12)->default('draft');
                $t->unsignedInteger('revision')->default(1);
                $t->unsignedInteger('lock_version')->default(1);
                $t->json('document');
                $t->timestamps();
            });
        }
        Schema::create('enquiry_quotation_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enquiry_quotation_id')->constrained('enquiry_quotations')->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->json('document');
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('sent_at');
            $t->dateTime('expires_at');
            $t->foreignId('support_message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $t->timestamps();
            $t->unique(['enquiry_quotation_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_quotation_versions');
        Schema::dropIfExists('enquiry_quotations');
    }
};
