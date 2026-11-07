<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Tests\TestCase;

/**
 * Tests that refresh the database use the default connection, it must never be the real database.
 */
final class TestEnvironmentTest extends TestCase
{
    public function testCachedConfigIsNotUsed(): void
    {
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('testing', config('database.default'));
    }
}
