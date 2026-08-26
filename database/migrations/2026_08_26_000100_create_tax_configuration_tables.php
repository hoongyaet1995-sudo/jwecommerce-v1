<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_configurations', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('prices_include_tax')->default(false);
            $table->string('address_basis')->nullable();
            $table->json('merchant_compliance_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('country');
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_zone_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->string('tax_type');
            $table->decimal('rate_percent', 8, 4);
            $table->boolean('applies_to_shipping')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('tax_class_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });

        Schema::create('order_tax_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('tax_type');
            $table->decimal('rate_percent', 8, 4);
            $table->unsignedInteger('taxable_amount');
            $table->unsignedInteger('tax_amount');
            $table->json('configuration_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tax_lines');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_class_id');
        });

        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_classes');
        Schema::dropIfExists('tax_zones');
        Schema::dropIfExists('tax_configurations');
    }
};
