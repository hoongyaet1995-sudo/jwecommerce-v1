<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropTrigger();

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER order_tax_lines_prevent_updates BEFORE UPDATE ON order_tax_lines FOR EACH ROW BEGIN IF NOT (NEW.order_id <=> OLD.order_id AND NEW.label <=> OLD.label AND NEW.tax_type <=> OLD.tax_type AND NEW.rate_percent <=> OLD.rate_percent AND NEW.taxable_amount <=> OLD.taxable_amount AND NEW.tax_amount <=> OLD.tax_amount AND NEW.configuration_snapshot <=> OLD.configuration_snapshot AND ((NEW.tax_rule_id <=> OLD.tax_rule_id) OR (OLD.tax_rule_id IS NOT NULL AND NEW.tax_rule_id IS NULL)) AND ((NEW.tax_class_id <=> OLD.tax_class_id) OR (OLD.tax_class_id IS NOT NULL AND NEW.tax_class_id IS NULL))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order tax lines are immutable'; END IF; END");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER order_tax_lines_prevent_updates BEFORE UPDATE ON order_tax_lines FOR EACH ROW WHEN NOT (NEW.order_id IS OLD.order_id AND NEW.label IS OLD.label AND NEW.tax_type IS OLD.tax_type AND NEW.rate_percent IS OLD.rate_percent AND NEW.taxable_amount IS OLD.taxable_amount AND NEW.tax_amount IS OLD.tax_amount AND NEW.configuration_snapshot IS OLD.configuration_snapshot AND ((NEW.tax_rule_id IS OLD.tax_rule_id) OR (OLD.tax_rule_id IS NOT NULL AND NEW.tax_rule_id IS NULL)) AND ((NEW.tax_class_id IS OLD.tax_class_id) OR (OLD.tax_class_id IS NOT NULL AND NEW.tax_class_id IS NULL))) BEGIN SELECT RAISE(ABORT, 'Order tax lines are immutable'); END");
        }
    }

    public function down(): void
    {
        $this->dropTrigger();

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER order_tax_lines_prevent_updates BEFORE UPDATE ON order_tax_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order tax lines are immutable'");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER order_tax_lines_prevent_updates BEFORE UPDATE ON order_tax_lines FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'Order tax lines are immutable'); END");
        }
    }

    private function dropTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS order_tax_lines_prevent_updates');
    }
};
