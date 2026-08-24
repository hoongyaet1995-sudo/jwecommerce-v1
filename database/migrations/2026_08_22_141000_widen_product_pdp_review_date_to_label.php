<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_pdp_reviews', function (Blueprint $table): void {
            $table->string('review_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_pdp_reviews', function (Blueprint $table): void {
            $table->date('review_date')->nullable()->change();
        });
    }
};
