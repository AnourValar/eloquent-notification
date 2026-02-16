<?php

namespace AnourValar\EloquentNotification\Adapters\Push;

class MockAdapter implements PushInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Push\PushInterface::fromConfig()
     */
    public function fromConfig(array $config): self
    {
        return clone $this;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Push\PushInterface::sendMessage()
     */
    public function sendMessage(string $receiver, string $title, string $body, array $data = []): void
    {
        //\Log::info('Push: ' . $title . ' -> ' . $body);
    }
}
