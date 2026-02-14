<?php

namespace AnourValar\EloquentNotification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
    * Create a new notification instance.
    */
    public function __construct(public string $code, public array $params = [])
    {
        $this->afterCommit();
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \Carbon\CarbonInterface
     */
    public function retryUntil()
    {
        return now()->addMinutes(5);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'sms'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(trans($this->params['subject'] ?? 'eloquent_notification::confirm.notification.mail.subject'))
            ->markdown($this->params['markdown'] ?? 'eloquent_notification::markdown.confirm.email', ['code' => $this->code, ...$this->params]);
    }

    /**
     * Get the sms representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return trans($this->params['message'] ?? 'eloquent_notification::confirm.notification.sms', ['code' => $this->code, ...$this->params]);
    }
}
