<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;
use AnourValar\EloquentNotification\Jobs\CollectNotificationJob;

class ServiceTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @return void
     */
    public function test_sync()
    {
        $class = config('auth.providers.users.model');
        $user1 = tap((new $class()))->save();
        $user2 = tap((new $class()))->save();

        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'foo', 'channels' => ['sms', 'telegram']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'bar', 'channels' => ['mail']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'baz', 'channels' => ['mail', 'sms', 'telegram']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'baz', 'channels' => ['mail', 'sms', 'telegram']]);


        // Iteration 1
        \App::make(\AnourValar\EloquentNotification\Service::class)->sync($user1, [
            'foo' => ['mail'],
            'bar' => ['sms'],
            'foobar' => ['telegram'],
        ]);

        $this->assertSame(
            [
                ['trigger' => 'foo', 'channels' => ['mail']],
                ['trigger' => 'bar', 'channels' => ['sms']],
                ['trigger' => 'foobar', 'channels' => ['telegram']],
            ],
            \AnourValar\EloquentNotification\UserNotification::where('user_id', '=', $user1->id)->get(['trigger', 'channels'])->toArray()
        );

        $this->assertSame(
            [
                ['trigger' => 'baz', 'channels' => ['mail', 'sms', 'telegram']],
            ],
            \AnourValar\EloquentNotification\UserNotification::where('user_id', '=', $user2->id)->get(['trigger', 'channels'])->toArray()
        );


        // Iteration 2
        \App::make(\AnourValar\EloquentNotification\Service::class)->sync($user1, [
            'foo' => ['mail', 'sms'],
        ], null, 'is_public');

        $this->assertSame(
            [
                ['trigger' => 'foo', 'channels' => ['mail', 'sms']],
                ['trigger' => 'foobar', 'channels' => ['telegram']],
            ],
            \AnourValar\EloquentNotification\UserNotification::where('user_id', '=', $user1->id)->get(['trigger', 'channels'])->toArray()
        );


        // Iteration 3
        $this->expectException(\AnourValar\EloquentValidation\Exceptions\ValidationException::class);
        \App::make(\AnourValar\EloquentNotification\Service::class)->sync($user1, [
            'foobar' => ['mail', 'sms'],
        ], null, 'is_public');



        // Iteration 4
        \App::make(\AnourValar\EloquentNotification\Service::class)->sync($user1, [], null, 'is_public');

        $this->assertSame(
            [
                ['trigger' => 'foobar', 'channels' => ['telegram']],
            ],
            \AnourValar\EloquentNotification\UserNotification::where('user_id', '=', $user1->id)->get(['trigger', 'channels'])->toArray()
        );


        // Iteration 5
        \App::make(\AnourValar\EloquentNotification\Service::class)->sync($user1, []);

        $this->assertSame(
            [],
            \AnourValar\EloquentNotification\UserNotification::where('user_id', '=', $user1->id)->get(['trigger', 'channels'])->toArray()
        );
    }

    /**
     * @return void
     */
    public function test_channels()
    {
        \App::setLocale('ru');

        config(['notification.trigger' => [
            'foo' => ['channels' => ['database', 'mail']],
            'bar' => ['channels' => ['mail', 'sms']],
            'baz' => ['channels' => ['sms']],
        ]]);

        $this->assertSame(
            [
                'database' => 'ЛК',
                'mail' => 'E-mail',
                'sms' => 'SMS',
            ],
            \App::make(\AnourValar\EloquentNotification\Service::class)->channels()
        );
    }

    /**
     * @return void
     */
    public function test_collectNotify()
    {
        \Queue::fake();
        $class = config('auth.providers.users.model');
        $user1 = tap((new $class()))->save();

        $mock = \Atom::partialMock();
        $arg = ['notification' => FooNotification::class, 'arguments' => ['foo']];
        $mock->shouldReceive('exchangerPush')->once()->with("notification_package:collect_notify:{$user1->id}", $arg);

        \App::make(\AnourValar\EloquentNotification\Service::class)->collectNotify(
            $user1,
            FooNotification::class,
            ['foo']
        );

        \Queue::assertPushed(CollectNotificationJob::class, 1);
        \Queue::assertPushed(CollectNotificationJob::class, fn ($job) => $job->user->id == $user1->id);
    }
}
