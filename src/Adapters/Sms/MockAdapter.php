<?php

namespace AnourValar\EloquentNotification\Adapters\Sms;

class MockAdapter implements SmsInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Sms\SmsInterface::sendMessage()
     */
    public function sendMessage(string $phone, string $message): void
    {
        //\Log::info('SMS: ' . $message);
    }
}
