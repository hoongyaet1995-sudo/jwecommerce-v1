<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_operational_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->string('reason_code', 64);
            $table->text('internal_note');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_operational_amendments');
    }
};
