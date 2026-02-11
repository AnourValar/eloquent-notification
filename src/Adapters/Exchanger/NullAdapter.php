<?php

namespace AnourValar\EloquentNotification\Adapters\Exchanger;

class NullAdapter implements ExchangerInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface::sendMessage()
     */
    public function sendMessage(string $title, string $body, string $tag = 'default', bool $html = false): void
    {

    }
}
