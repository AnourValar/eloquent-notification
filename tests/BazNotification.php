<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;

class BazNotification extends \AnourValar\EloquentNotification\AbstractNotification
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
     * @see \AnourValar\EloquentNotification\AbstractNotification::preventDuplicates()
     */
    protected function preventDuplicates(): int
    {
        return 10;
    }
}
