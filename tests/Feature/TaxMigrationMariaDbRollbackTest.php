<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaxMigrationMariaDbRollbackTest extends TestCase
{
    public function test_the_tax_migration_rolls_back_on_mariadb(): void
    {
        if (! extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('The MariaDB rollback test requires pdo_mysql.');
        }

        $database = 'jw_tax_rollback_'.Str::lower(Str::random(12));
        $serverConnection = 'tax_mariadb_rollback_server';
        $databaseConnection = 'tax_mariadb_rollback_database';
        $originalDefaultConnection = config('database.default');
        $serverConfig = config('database.connections.mysql');
        $serverConfig['database'] = null;
        $databaseConfig = config('database.connections.mysql');
        $databaseConfig['database'] = $database;

        config([
            "database.connections.{$serverConnection}" => $serverConfig,
            "database.connections.{$databaseConnection}" => $databaseConfig,
            'database.default' => $databaseConnection,
        ]);

        try {
            DB::purge($serverConnection);
            DB::connection($serverConnection)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            DB::purge($databaseConnection);

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $databaseConnection,
                '--path' => database_path('migrations/0001_01_01_000000_create_users_table.php'),
                '--realpath' => true,
                '--force' => true,
            ]));
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $databaseConnection,
                '--path' => database_path('migrations/2026_08_15_000100_create_commerce_domain_tables.php'),
                '--realpath' => true,
                '--force' => true,
            ]));
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $databaseConnection,
                '--path' => database_path('migrations/2026_08_26_000100_create_tax_configuration_tables.php'),
                '--realpath' => true,
                '--force' => true,
            ]));

            $this->assertTrue(Schema::connection($databaseConnection)->hasTable('tax_configurations'));

            $this->assertSame(0, Artisan::call('migrate:rollback', [
                '--database' => $databaseConnection,
                '--path' => database_path('migrations/2026_08_26_000100_create_tax_configuration_tables.php'),
                '--realpath' => true,
                '--step' => 1,
                '--force' => true,
            ]));

            $this->assertFalse(Schema::connection($databaseConnection)->hasTable('tax_configurations'));
            $this->assertFalse(Schema::connection($databaseConnection)->hasColumn('products', 'tax_class_id'));
        } finally {
            DB::purge($databaseConnection);
            DB::purge($serverConnection);
            config(['database.default' => $originalDefaultConnection]);
            DB::connection($serverConnection)->statement("DROP DATABASE IF EXISTS `{$database}`");
            DB::purge($serverConnection);
        }
    }
}
