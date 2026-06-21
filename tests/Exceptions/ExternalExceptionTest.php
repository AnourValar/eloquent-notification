<?php

namespace AnourValar\EloquentNotification\Tests\Exceptions;

use AnourValar\EloquentNotification\Exceptions\Error;
use AnourValar\EloquentNotification\Exceptions\ExternalException;
use AnourValar\EloquentNotification\Tests\AbstractSuite;

class ExternalExceptionTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_defaults()
    {
        $e = new ExternalException('foo');

        $this->assertSame('Unexpected behaviour for action foo.', $e->getMessage());
        $this->assertSame(Error::ETC, $e->error);
        $this->assertSame([], $e->context());
    }

    /**
     * @return void
     */
    public function test_with_dump_and_error()
    {
        $e = new ExternalException('bar', ['key' => 'value'], Error::USER_BLOCK);

        $this->assertSame(Error::USER_BLOCK, $e->error);
        $this->assertSame(['key' => 'value'], $e->context());
    }

    /**
     * @return void
     */
    public function test_report()
    {
        \Log::spy();

        (new ExternalException('baz', ['key' => 'value']))->report();

        \Log::shouldHaveReceived('info')->once()->with('Unexpected behaviour for action baz.', ['key' => 'value']);
    }

    /**
     * @return void
     */
    public function test_report_without_dump()
    {
        \Log::spy();

        (new ExternalException('baz'))->report();

        \Log::shouldHaveReceived('info')->once()->with('Unexpected behaviour for action baz.', []);
    }
}
