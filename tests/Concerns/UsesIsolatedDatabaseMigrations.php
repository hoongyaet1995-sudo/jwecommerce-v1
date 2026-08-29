<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use LogicException;

trait UsesIsolatedDatabaseMigrations
{
    private string $originalDatabaseConnection;

    /** @var array<string, mixed> */
    private array $originalMysqlConfiguration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedMysqlDatabase();
        $this->migrateIsolatedDatabase();
    }

    protected function tearDown(): void
    {
        try {
            $this->clearIsolatedDatabase();
        } finally {
            $this->restoreOriginalDatabaseConnection();
            parent::tearDown();
        }
    }

    private function useIsolatedMysqlDatabase(): void
    {
        $this->originalDatabaseConnection = (string) config('database.default');
        $this->originalMysqlConfiguration = config('database.connections.mysql');

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'jw_ecommerce_testing',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);

        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        DB::reconnect('mysql');
    }

    private function restoreOriginalDatabaseConnection(): void
    {
        DB::purge('mysql');

        config([
            'database.default' => $this->originalDatabaseConnection,
            'database.connections.mysql' => $this->originalMysqlConfiguration,
        ]);

        DB::setDefaultConnection($this->originalDatabaseConnection);
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

        $this->clearIsolatedDatabase();
    }

    private function clearIsolatedDatabase(): void
    {
        if (config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'jw_ecommerce_testing') {
            throw new LogicException('EasyParcel feature tests require the jw_ecommerce_testing database.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tableMetadata() as $metadata) {
                $table = (string) $metadata->table_name;
                $autoIncrement = (int) ($metadata->auto_increment ?? 1);

                if ($table !== 'migrations'
                    && ($autoIncrement > 1 || DB::table($table)->exists())) {
                    DB::table($table)->truncate();
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
            $this->tableMetadata(),
        );
    }

    /** @return list<object{table_name: string, auto_increment: int|null}> */
    private function tableMetadata(): array
    {
        return DB::select(
            'SELECT table_name, auto_increment FROM information_schema.tables WHERE table_schema = ? AND table_type = ?',
            [DB::getDatabaseName(), 'BASE TABLE'],
        );
    }
}
