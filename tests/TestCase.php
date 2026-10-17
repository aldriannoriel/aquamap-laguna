<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        if (! $app->runningUnitTests()
            || $connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Tests require SQLite :memory:; no persistent database may be used.');
        }

        return $app;
    }
}
