<?php

namespace AnourValar\EloquentNotification\Drivers;

use Illuminate\Notifications\Notification;
use AnourValar\EloquentNotification\Adapters\Sms\SmsInterface;

class SmsChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification);
        if (is_null($phone)) {
            return;
        }

        \App::make(SmsInterface::class)->sendMessage($phone, $notification->toSms($notifiable));
    }
}
