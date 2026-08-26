<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_configurations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('prices_include_tax')->default(false);
            $table->string('address_basis')->nullable();
            $table->json('merchant_compliance_metadata')->nullable();
            $table->timestamps();
        });

        $this->createTaxConfigurationSingletonConstraint();

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

        $this->createOrderTaxLineImmutabilityTrigger();
    }

    public function down(): void
    {
        $this->dropOrderTaxLineImmutabilityTrigger();
        Schema::dropIfExists('order_tax_lines');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_class_id');
        });

        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_classes');
        Schema::dropIfExists('tax_zones');
        $this->dropTaxConfigurationSingletonConstraint();
        Schema::dropIfExists('tax_configurations');
    }

    private function createTaxConfigurationSingletonConstraint(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('ALTER TABLE tax_configurations ADD CONSTRAINT tax_configurations_singleton_key_check CHECK (singleton_key = 1)');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS tax_configurations_singleton_key_insert_check');
            DB::unprepared('DROP TRIGGER IF EXISTS tax_configurations_singleton_key_update_check');
            DB::unprepared("CREATE TRIGGER tax_configurations_singleton_key_insert_check BEFORE INSERT ON tax_configurations FOR EACH ROW WHEN NEW.singleton_key <> 1 BEGIN SELECT RAISE(ABORT, 'Tax configuration singleton key must be 1'); END");
            DB::unprepared("CREATE TRIGGER tax_configurations_singleton_key_update_check BEFORE UPDATE ON tax_configurations FOR EACH ROW WHEN NEW.singleton_key <> 1 BEGIN SELECT RAISE(ABORT, 'Tax configuration singleton key must be 1'); END");
        }
    }

    private function dropTaxConfigurationSingletonConstraint(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('ALTER TABLE tax_configurations DROP CHECK tax_configurations_singleton_key_check');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS tax_configurations_singleton_key_insert_check');
            DB::unprepared('DROP TRIGGER IF EXISTS tax_configurations_singleton_key_update_check');
        }
    }

    private function createOrderTaxLineImmutabilityTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS order_tax_lines_prevent_updates');

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER order_tax_lines_prevent_updates BEFORE UPDATE ON order_tax_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order tax lines are immutable'");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER order_tax_lines_prevent_updates BEFORE UPDATE ON order_tax_lines FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'Order tax lines are immutable'); END");
        }
    }

    private function dropOrderTaxLineImmutabilityTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS order_tax_lines_prevent_updates');
    }
};
