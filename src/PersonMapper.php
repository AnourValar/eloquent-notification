<?php

namespace AnourValar\EloquentNotification;

use Illuminate\Contracts\Translation\HasLocalePreference;

class PersonMapper implements HasLocalePreference
{
    use \Illuminate\Notifications\RoutesNotifications;

    /**
     * @return void
     * @throws \RuntimeException
     */
    public function __construct(
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $locale = null,
    ) {
        if (! isset($email) && ! isset($phone)) {
            throw new \RuntimeException('Incorrect usage.');
        }
    }

    /**
     * @return string|null
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Route notifications for the Email channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return string|null
     */
    public function routeNotificationForMail($notification)
    {
        return $this->email;
    }

    /**
     * Route notifications for the Sms channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return string|null
     */
    public function routeNotificationForSms($notification)
    {
        return $this->phone;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->email ?? $this->phone;
    }
}
