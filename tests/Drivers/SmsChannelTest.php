<?php

namespace AnourValar\EloquentNotification\Tests\Drivers;

use AnourValar\EloquentNotification\Adapters\Sms\SmsInterface;
use AnourValar\EloquentNotification\Drivers\SmsChannel;
use AnourValar\EloquentNotification\Tests\AbstractSuite;

class SmsChannelTest extends AbstractSuite
{
    /**
     * No phone → adapter is not touched.
     *
     * @return void
     */
    public function test_send_without_phone()
    {
        $this->mock(SmsInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
        });

        \App::make(SmsChannel::class)->send($this->makeNotifiable(null), $this->makeNotification());
    }

    /**
     * Happy path: SMS is delivered with the rendered text.
     *
     * @return void
     */
    public function test_send_success()
    {
        $this->mock(SmsInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->with('79001234567', 'Hello, World!');
        });

        \App::make(SmsChannel::class)->send($this->makeNotifiable('79001234567'), $this->makeNotification());
    }

    /**
     * @param string|null $phone
     * @return object
     */
    private function makeNotifiable(?string $phone): object
    {
        return new class ($phone)
        {
            public function __construct(private ?string $phone)
            {
                //
            }

            public function routeNotificationFor($channel, $notification = null)
            {
                return $this->phone;
            }
        };
    }

    /**
     * @return \Illuminate\Notifications\Notification
     */
    private function makeNotification(): \Illuminate\Notifications\Notification
    {
        return new class () extends \Illuminate\Notifications\Notification
        {
            public function toSms($notifiable): string
            {
                return 'Hello, World!';
            }
        };
    }
}
