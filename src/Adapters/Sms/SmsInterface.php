<?php

namespace AnourValar\EloquentNotification\Adapters\Sms;

interface SmsInterface
{
    /**
     * Send a message
     *
     * @param string $phone
     * @param string $message
     * @return void
     * @throws \AnourValar\EloquentNotification\Exceptions\ExternalException
     */
    public function sendMessage(string $phone, string $message): void;
}
