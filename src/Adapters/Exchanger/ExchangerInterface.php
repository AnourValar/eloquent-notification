<?php

namespace AnourValar\EloquentNotification\Adapters\Exchanger;

interface ExchangerInterface
{
    /**
     * Send a message
     *
     * @param string $title
     * @param string $body
     * @param string $tag
     * @param bool $html
     * @return void
     * @throws \AnourValar\EloquentNotification\Exceptions\ExternalException
     */
    public function sendMessage(string $title, string $body, string $tag = 'default', bool $html = false): void;
}
