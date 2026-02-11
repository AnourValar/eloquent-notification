<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;

class BarNotification extends \AnourValar\EloquentNotification\AbstractNotification
{
    public function __construct(public int $param)
    {
        parent::__construct();
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
