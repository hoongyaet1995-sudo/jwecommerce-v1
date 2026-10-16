<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_acceptances', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('enquiry_quotation_id')->unique()->constrained('enquiry_quotations')->restrictOnDelete();
            $t->foreignUuid('enquiry_quotation_version_id')->unique()->constrained('enquiry_quotation_versions')->restrictOnDelete();
            $t->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('accepted_at');
            $t->foreignId('order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_acceptances');
    }
};
