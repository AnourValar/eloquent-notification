<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;

class FooNotification extends \AnourValar\EloquentNotification\AbstractNotification
{
    /**
     * @param array $arg
     * @return void
     */
    public function __construct(public array $arg1 = [], public array $arg2 = [])
    {

    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\AbstractNotification::cacheChannels()
     */
    protected function cacheChannels(): int
    {
        return 0;
    }
}
