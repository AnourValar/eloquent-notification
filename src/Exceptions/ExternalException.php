<?php

namespace AnourValar\EloquentNotification\Exceptions;

use Exception;

class ExternalException extends Exception
{
    /**
     * @var \AnourValar\EloquentNotification\Exceptions\Error
     */
    public readonly Error $error;

    /**
     * @var array|null
     */
    protected ?array $dump;

    /**
     * @param string $action
     * @param array|null $dump
     * @param \AnourValar\EloquentNotification\Exceptions\Error $error
     * @return void
     */
    public function __construct(string $action, ?array $dump = null, Error $error = Error::ETC)
    {
        parent::__construct("Unexpected behaviour for action {$action}.");

        $this->dump = $dump;
        $this->error = $error;
    }

    /**
     * Report the exception.
     *
     * @return void
     */
    public function report()
    {
        \Log::info($this->getMessage(), $this->dump ?? []);
    }

    /**
     * Horizon
     *
     * @return array
     */
    public function context()
    {
        return $this->dump ?? [];
    }
}
