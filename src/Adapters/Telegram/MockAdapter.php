<?php

namespace AnourValar\EloquentNotification\Adapters\Telegram;

class MockAdapter implements TelegramInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::fromConfig()
     */
    public function fromConfig(array $config): self
    {
        return clone $this;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::sendMessage()
     */
    public function sendMessage(string $chatId, string $message): void
    {
        //\Log::info('Telegram: ' . $message);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::fetchChatId()
     */
    public function fetchChatId(string $username): ?string
    {
        return '@' . mb_strtolower($username);
    }
}
