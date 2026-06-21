<?php

namespace AnourValar\EloquentNotification\Tests\Drivers;

use AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface;
use AnourValar\EloquentNotification\Drivers\TelegramChannel;
use AnourValar\EloquentNotification\Events\TelegramUsernameBlocked;
use AnourValar\EloquentNotification\Exceptions\Error;
use AnourValar\EloquentNotification\Exceptions\ExternalException;
use AnourValar\EloquentNotification\Tests\AbstractSuite;

class TelegramChannelTest extends AbstractSuite
{
    /**
     * No chatId → adapter is not even touched.
     *
     * @return void
     */
    public function test_send_without_chat_id()
    {
        $this->mock(TelegramInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
        });

        \App::make(TelegramChannel::class)->send($this->makeNotifiable(null), $this->makeNotification());
    }

    /**
     * Happy path: message is delivered, no events.
     *
     * @return void
     */
    public function test_send_success()
    {
        \Event::fake();

        $this->mock(TelegramInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->with('123', 'Hello, World!');
        });

        \App::make(TelegramChannel::class)->send($this->makeNotifiable('123'), $this->makeNotification());

        \Event::assertNotDispatched(TelegramUsernameBlocked::class);
    }

    /**
     * USER_BLOCK → dispatches the event and re-throws.
     *
     * @return void
     */
    public function test_send_user_block()
    {
        \Event::fake();

        $notifiable = $this->makeNotifiable('123');

        $this->mock(TelegramInterface::class, function ($mock) {
            $mock
                ->shouldReceive('sendMessage')
                ->once()
                ->andThrow(new ExternalException('telegram', null, Error::USER_BLOCK));
        });

        $this->expectException(ExternalException::class);
        try {
            \App::make(TelegramChannel::class)->send($notifiable, $this->makeNotification());
        } catch (ExternalException $e) {
            $this->assertSame(Error::USER_BLOCK, $e->error);
            \Event::assertDispatched(TelegramUsernameBlocked::class, fn ($event) => $event->user === $notifiable);
            throw $e;
        }
    }

    /**
     * Any other (ETC) error → re-thrown without the "blocked" event.
     *
     * @return void
     */
    public function test_send_generic_error()
    {
        \Event::fake();

        $this->mock(TelegramInterface::class, function ($mock) {
            $mock
                ->shouldReceive('sendMessage')
                ->once()
                ->andThrow(new ExternalException('telegram', null, Error::ETC));
        });

        $this->expectException(ExternalException::class);
        try {
            \App::make(TelegramChannel::class)->send($this->makeNotifiable('123'), $this->makeNotification());
        } catch (ExternalException $e) {
            $this->assertSame(Error::ETC, $e->error);
            \Event::assertNotDispatched(TelegramUsernameBlocked::class);
            throw $e;
        }
    }

    /**
     * @param string|null $chatId
     * @return object
     */
    private function makeNotifiable(?string $chatId): object
    {
        return new class ($chatId)
        {
            public function __construct(private ?string $chatId)
            {
                //
            }

            public function routeNotificationFor($channel, $notification = null)
            {
                return $this->chatId;
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
            public function toTelegram($notifiable): string
            {
                return 'Hello, World!';
            }
        };
    }
}
