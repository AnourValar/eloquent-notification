<?php

namespace AnourValar\EloquentNotification\Adapters\Telegram;

use Illuminate\Mail\Markdown;
use AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface;

class ExchangerAdapter extends MockAdapter
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::sendMessage()
     */
    public function sendMessage(string $chatId, string $message): void
    {
        \App::make(ExchangerInterface::class)->sendMessage($chatId, (string) Markdown::parse($message), 'TELEGRAM', true);
    }
}
