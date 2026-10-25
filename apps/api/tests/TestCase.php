<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Hard safety gate: no test may ever run against the protected database.
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($database === 'nu') {
            $this->fail(
                'Refusing to run tests against the protected database `nu`. '.
                'Tests must target the isolated `nu_test` database (see phpunit.xml).'
            );
        }
    }
}