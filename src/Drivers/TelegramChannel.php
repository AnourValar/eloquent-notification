<?php

namespace AnourValar\EloquentNotification\Drivers;

use Illuminate\Notifications\Notification;
use AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface;

class TelegramChannel // @TODO: tests
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = $notifiable->routeNotificationFor('telegram', $notification);
        if (is_null($chatId)) {
            return;
        }

        try {
            \App::make(TelegramInterface::class)->sendMessage($chatId, $notification->toTelegram($notifiable));
        } catch (\AnourValar\EloquentNotification\Exceptions\ExternalException $e) {
            if ($e->error == \AnourValar\EloquentNotification\Exceptions\Error::USER_BLOCK) {
                event(new \AnourValar\EloquentNotification\Events\TelegramUsernameBlocked($notifiable));
            }

            throw $e;
        }
    }
}
