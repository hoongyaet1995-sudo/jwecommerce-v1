<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use LogicException;

trait UsesIsolatedDatabaseMigrations
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateIsolatedDatabase();
    }

    private function migrateIsolatedDatabase(): void
    {
        if (config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'jw_ecommerce_testing') {
            throw new LogicException('EasyParcel feature tests require the jw_ecommerce_testing database.');
        }

        if (! $this->tableExists('migrations') || DB::table('migrations')->count() === 0) {
            $this->artisan('migrate');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tableNames() as $table) {
                if ($table !== 'migrations') {
                    DB::table($table)->delete();
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, $this->tableNames(), true);
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        return array_map(
            fn (object $row): string => (string) $row->table_name,
            DB::select(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ?',
                [DB::getDatabaseName(), 'BASE TABLE'],
            ),
        );
    }
}
