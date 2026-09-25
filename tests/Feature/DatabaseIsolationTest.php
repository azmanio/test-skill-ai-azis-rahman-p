<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_tests_use_the_separate_postgresql_database(): void
    {
        $this->assertSame('jualemas_test', config('database.connections.pgsql.database'));
    }
}
