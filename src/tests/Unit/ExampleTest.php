<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function testThatTrueIsTrue(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+/', PHP_VERSION);
    }
}
