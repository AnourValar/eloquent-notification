<?php

namespace AnourValar\EloquentNotification\Adapters\Telegram;

interface TelegramInterface
{
    /**
     * Create a new instance with a custom config
     *
     * @param array $config
     * @return self
     */
    public function fromConfig(array $config): self;

    /**
     * Send a message
     *
     * @param string $chatId
     * @param string $message
     * @return void
     * @throws \AnourValar\EloquentNotification\Exceptions\ExternalException
     */
    public function sendMessage(string $chatId, string $message): void;

    /**
     * Check & Fetch a user's chatID (with a bot)
     *
     * @param string $username
     * @return string|null
     * @throws \AnourValar\EloquentNotification\Exceptions\ExternalException
     */
    public function fetchChatId(string $username): ?string;
}
