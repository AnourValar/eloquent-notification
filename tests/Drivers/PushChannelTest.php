<?php

namespace AnourValar\EloquentNotification\Tests\Drivers;

use AnourValar\EloquentNotification\Adapters\Push\PushInterface;
use AnourValar\EloquentNotification\Drivers\PushChannel;
use AnourValar\EloquentNotification\Tests\AbstractSuite;

class PushChannelTest extends AbstractSuite
{
    /**
     * No tokens → adapter is not touched.
     *
     * @return void
     */
    public function test_send_without_tokens()
    {
        $this->mock(PushInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
        });

        \App::make(PushChannel::class)->send($this->makeNotifiable(null), $this->makeNotification());
        \App::make(PushChannel::class)->send($this->makeNotifiable([]), $this->makeNotification());
        \App::make(PushChannel::class)->send($this->makeNotifiable(['', null, 0]), $this->makeNotification());
    }

    /**
     * Empty tokens are filtered out, a message goes to each remaining one.
     *
     * @return void
     */
    public function test_send_filters_empty_tokens()
    {
        $this->mock(PushInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->with('token-a', 'Title', 'Body', ['key' => 'value']);
            $mock->shouldReceive('sendMessage')->once()->with('token-b', 'Title', 'Body', ['key' => 'value']);
        });

        \App::make(PushChannel::class)->send(
            $this->makeNotifiable(['token-a', '', 'token-b', null]),
            $this->makeNotification(['title' => 'Title', 'body' => 'Body', 'data' => ['key' => 'value']])
        );
    }

    /**
     * Missing "data" key defaults to an empty array.
     *
     * @return void
     */
    public function test_send_without_data()
    {
        $this->mock(PushInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->with('token-a', 'Title', 'Body', []);
        });

        \App::make(PushChannel::class)->send(
            $this->makeNotifiable(['token-a']),
            $this->makeNotification(['title' => 'Title', 'body' => 'Body'])
        );
    }

    /**
     * No more than 10 devices are notified.
     *
     * @return void
     */
    public function test_send_limits_to_ten_tokens()
    {
        $this->mock(PushInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->times(10);
        });

        $tokens = array_map(fn ($i) => "token-{$i}", range(1, 12));

        \App::make(PushChannel::class)->send(
            $this->makeNotifiable($tokens),
            $this->makeNotification(['title' => 'Title', 'body' => 'Body'])
        );
    }

    /**
     * @param mixed $tokens
     * @return object
     */
    private function makeNotifiable($tokens): object
    {
        return new class ($tokens)
        {
            public function __construct(private $tokens)
            {
                //
            }

            public function routeNotificationFor($channel, $notification = null)
            {
                return $this->tokens;
            }
        };
    }

    /**
     * @param array $payload
     * @return \Illuminate\Notifications\Notification
     */
    private function makeNotification(array $payload = []): \Illuminate\Notifications\Notification
    {
        return new class ($payload) extends \Illuminate\Notifications\Notification
        {
            public function __construct(private array $payload)
            {
                //
            }

            public function toPush($notifiable): array
            {
                return $this->payload;
            }
        };
    }
}
