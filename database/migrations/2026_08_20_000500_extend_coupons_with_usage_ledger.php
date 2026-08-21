<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('code');
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('usage_limit')->nullable()->after('min_spend');
            $table->unsignedInteger('usage_count')->default(0)->after('usage_limit');
            $table->unsignedInteger('per_customer_limit')->default(1)->after('usage_count');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
        });
        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email');
            $table->unsignedInteger('discount_amount');
            $table->timestamps();
            $table->index(['coupon_id', 'customer_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });
        Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn(['name', 'description', 'usage_limit', 'usage_count', 'per_customer_limit']));
    }
};
