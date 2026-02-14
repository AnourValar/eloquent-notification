<?php

namespace AnourValar\EloquentNotification\Adapters\Exchanger;

class MailAdapter implements ExchangerInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface::sendMessage()
     */
    public function sendMessage(string $title, string $body, string $tag = 'default', bool $html = false): void
    {
        $config = config('eloquent_notification.bindings.' . ExchangerInterface::class . '.mail');
        $method = $html ? 'html' : 'raw';
        $from = $config['from'] ?? config('mail.from.address');
        $to = $config['to'] ?? ($tag . '@example.org');

        \Mail::build($config)->$method($body, fn ($message) => $message->from($from, $tag)->to($to)->subject($title));
    }
}
