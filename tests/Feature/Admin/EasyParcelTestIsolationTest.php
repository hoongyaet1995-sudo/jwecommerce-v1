<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class EasyParcelTestIsolationTest extends TestCase
{
    public function test_easyparcel_feature_tests_do_not_opt_into_destructive_database_migrations(): void
    {
        foreach ([
            'EasyParcelProviderApiTest.php',
            'EasyParcelPlatformOauthTest.php',
            'EasyParcelFulfillmentApiTest.php',
        ] as $filename) {
            $source = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.$filename);

            $this->assertStringContainsString('UsesIsolatedDatabaseMigrations', $source, $filename.' must enforce the isolated PHPUnit database.');
        }
    }
}
