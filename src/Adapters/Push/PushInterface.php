<?php

namespace AnourValar\EloquentNotification\Adapters\Push;

interface PushInterface // @TODO: group (topic)
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
     * @param string $receiver
     * @param string $title
     * @param string $body
     * @param array $data
     * @return void
     * @throws \AnourValar\EloquentNotification\Exceptions\ExternalException
     */
    public function sendMessage(string $receiver, string $title, string $body, array $data = []): void;

    // public function addToGroup(array $receivers, string $group): void;
    // public function removeFromGroup(array $receivers, string $group): void;
}
