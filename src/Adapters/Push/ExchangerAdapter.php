<?php

namespace AnourValar\EloquentNotification\Adapters\Push;

use AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface;

class ExchangerAdapter extends MockAdapter
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Push\PushInterface::sendMessage()
     */
    public function sendMessage(string $receiver, string $title, string $body, array $data = []): void
    {
        $receiver = \Str::limit($receiver, 40);
        $body = sprintf('<strong>%s</strong><br /><br />%s', $title, $body);

        \App::make(ExchangerInterface::class)->sendMessage($receiver, $body, 'PUSH', true);
    }
}
