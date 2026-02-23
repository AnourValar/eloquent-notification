<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification
    |--------------------------------------------------------------------------
    */

    'bindings' => [
        AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::class => [
            'bind' => env('ELOQUENT_NOTIFICATION_TELEGRAM_ADAPTER', AnourValar\EloquentNotification\Adapters\Telegram\TelegramAdapter::class),
            'token' => env('ELOQUENT_NOTIFICATION_TELEGRAM_TOKEN', ''),
            'name' => env('ELOQUENT_NOTIFICATION_TELEGRAM_NAME', 'NotificationBot'),
        ],

        AnourValar\EloquentNotification\Adapters\Sms\SmsInterface::class => [
            'bind' => env('ELOQUENT_NOTIFICATION_SMS_ADAPTER', AnourValar\EloquentNotification\Adapters\Sms\MtsAdapter::class),
            'mts_token' => env('ELOQUENT_NOTIFICATION_MTS_TOKEN', ''),
            'mts_sender' => env('ELOQUENT_NOTIFICATION_MTS_SENDER', 'MyApp'),
            'smsc_api_key' => env('ELOQUENT_NOTIFICATION_SMSC_API_TOKEN'),
            'smsc_sender' => env('ELOQUENT_NOTIFICATION_SMSC_SENDER', 'MyApp'),
        ],

        AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface::class => [
            'bind' => env('ELOQUENT_NOTIFICATION_EXCHANGER_ADAPTER', AnourValar\EloquentNotification\Adapters\Exchanger\NullAdapter::class),
            'mail' => ['transport' => 'smtp', 'host' => env('ELOQUENT_NOTIFICATION_MAIL_HOST'), 'port' => 1025, 'encryption' => 'tls'],
        ],

        AnourValar\EloquentNotification\Adapters\Push\PushInterface::class => [
            'bind' => env('ELOQUENT_NOTIFICATION_PUSH_ADAPTER', AnourValar\EloquentNotification\Adapters\Push\FCMAdapter::class),
            'fcm_service_account' => [
                'project_id' => env('ELOQUENT_NOTIFICATION_FCM_PROJECT_ID'),
                'private_key' => env('ELOQUENT_NOTIFICATION_FCM_PRIVATE_KEY'),
                'client_email' => env('ELOQUENT_NOTIFICATION_FCM_CLIENT_EMAIL'),
            ],
        ],
    ],

    'model' => App\UserNotification::class,
    'collect_delay_seconds' => 900, // 15 minutes

    'trigger' => [ // every trigger should have a unique handler (bind)
        /*'logged_in' => [
            'bind' => \App\Notifications\Trigger\LoggedInNotification::class,
            'title' => 'eloquent_notification::user_notification.trigger.logged_in',
            'channels' => ['sms', 'telegram', 'mail', 'push', 'database'],
            //'optgroup' => 'eloquent_notification::user_notification.trigger.user_optgroup',
        ],*/
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirm
    |--------------------------------------------------------------------------
    */

    'confirm' => [
        'pow_cost' => 375000,
        'pow_expire' => 60, // 1 minute
        'email_expire' => 1800, // 30 minutes
        'phone_expire' => 900, // 15 minutes
        'fa_expire' => 1800, // 30 minutes

        'notification' => AnourValar\EloquentNotification\Notifications\ConfirmNotification::class,

        'throttle' => [
            'request_email' => [['limit' => 2, 'seconds' => 60], ['limit' => 40, 'seconds' => 86400]], // per email
            'validate_email' => [['limit' => 5, 'seconds' => 1801]], // per cryptogram

            'request_phone' => [['limit' => 2, 'seconds' => 60], ['limit' => 30, 'seconds' => 86400]], // per phone
            'validate_phone' => [['limit' => 5, 'seconds' => 901]], // per cryptogram

            'totp_validate' => [['limit' => 2, 'seconds' => 60], ['limit' => 30, 'seconds' => 86400]], // per secret

            'fa_validate' => [['limit' => 1, 'seconds' => 1801]], // per cryptogram
        ],
    ],

];
