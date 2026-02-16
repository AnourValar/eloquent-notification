<?php

namespace AnourValar\EloquentNotification\Drivers;

use Illuminate\Notifications\Notification;
use AnourValar\EloquentNotification\Adapters\Push\PushInterface;

class PushChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = array_filter((array) $notifiable->routeNotificationFor('push', $notification));
        $tokens = array_slice($tokens, 0, 10); // too many devices
        if (! $tokens) {
            return;
        }

        $data = $notification->toPush($notifiable);
        $pushAdapter = \App::make(PushInterface::class);

        foreach ($tokens as $token) {
            $pushAdapter->sendMessage($token, $data['title'], $data['body'], ($data['data'] ?? [])); // @TODO "Error::USER_BLOCK" ?
        }
    }
}
