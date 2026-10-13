<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Fail before RefreshDatabase can migrate/drop any non-isolated database.
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests refused: only isolated SQLite :memory: is allowed. Never run RefreshDatabase against the development database.');
        }
        return $app;
    }
}
