<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $column = collect(Schema::getColumns('marketing_production_plans'))->firstWhere('name', 'expires_at');
        if (($column['type_name'] ?? '') === 'timestamp') {
            DB::statement('ALTER TABLE marketing_production_plans MODIFY expires_at DATETIME NOT NULL');
            // P2-A always grants exactly 24h from preparation. Restore this invariant
            // for rows written before the MySQL implicit timestamp behavior was caught.
            DB::statement('UPDATE marketing_production_plans SET expires_at = DATE_ADD(created_at, INTERVAL 1 DAY)');
        }
    }

    public function down(): void
    {
        // Never reinstate an implicit automatic expiry update.
    }
};
