# Laravel Eloquent Notification

## Installation

**Step #1: Install the package**

```bash
composer require anourvalar/eloquent-notification
```

**Step #2: Publish the resources**

```bash
php artisan vendor:publish --provider=AnourValar\\EloquentNotification\\Providers\\AnourValarEloquentNotificationServiceProvider
```


## Notification feature

**Step #1: Add notification routes for User model**

```php
/**
 * Route notifications for the Telegram channel.
 *
 * @param  \Illuminate\Notifications\Notification  $notification
 * @return string|null
 */
public function routeNotificationForTelegram($notification)
{
    return $this->telegram_chatid; // set your attribute
}

/**
 * Route notifications for the SMS channel.
 *
 * @param  \Illuminate\Notifications\Notification  $notification
 * @return string|null
 */
public function routeNotificationForSms($notification)
{
    return $this->phone; // set your attribute
}
```

**Step #2: Create a notification**

```php
namespace App\Notifications\Triggers;

use Illuminate\Notifications\Messages\MailMessage;

class NewPostNotification extends \AnourValar\EloquentNotification\AbstractNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public \App\Post $post)
    {
        parent::__construct();
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line($this->post->title)
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }
}
```

**Step #3: Collect (group) notify (optional)**

```php
App::make(Service::class)->collectNotify($user, EmailUpdatedNotification::class, []);
```


## Confirm feature

**Step #1: Add middleware to the HTTP Kernel**

```php
/**
 * The application's middleware aliases.
 *
 * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
 *
 * @var array<string, class-string|string>
 */
protected $middlewareAliases = [
    // <...>
    'confirm.pow' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmPow::class,
    'confirm.email.input' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmEmailInput::class,
    'confirm.email.my' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmEmailMy::class,
    'confirm.phone.input' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmPhoneInput::class,
    'confirm.phone.my' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmPhoneMy::class,
    'confirm.totp.input' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmTotpInput::class,
    'confirm.totp.my' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmTotpMy::class,
    'confirm.fa.my' => \AnourValar\EloquentNotification\Http\Middleware\ConfirmFaMy::class,
];
```

**Step #2: Explore the ConfirmService**
