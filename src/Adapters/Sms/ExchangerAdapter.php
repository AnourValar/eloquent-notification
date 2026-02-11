<?php

namespace AnourValar\EloquentNotification\Adapters\Sms;

use AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface;

class ExchangerAdapter implements SmsInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Sms\SmsInterface::sendMessage()
     */
    public function sendMessage(string $phone, string $message): void
    {
        \App::make(ExchangerInterface::class)->sendMessage($phone, $message, 'SMS', false);
    }
}
