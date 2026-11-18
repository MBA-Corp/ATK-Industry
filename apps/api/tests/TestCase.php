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
        $driver = $app['config']->get('database.default');
        $connection = $app['config']->get('database.connections.'.$driver);
        $safe = $driver === 'sqlite' && ($connection['database'] ?? '') === ':memory:';
        $safe = $safe || ($driver === 'mysql'
            && ($connection['host'] ?? '') === 'mysql-test'
            && ($connection['database'] ?? '') === 'atk_industry_test'
            && ($connection['username'] ?? '') === 'atk_test');
        if (! $app->environment('testing') || ! $safe || ! empty($connection['url'])) {
            throw new RuntimeException('Tests require SQLite :memory: or the isolated mysql-test/atk_industry_test service.');
        }

        return $app;
    }
}
