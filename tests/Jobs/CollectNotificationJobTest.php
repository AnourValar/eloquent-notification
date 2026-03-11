<?php

namespace AnourValar\EloquentNotification\Tests\Jobs;

use Tests\TestCase;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Jobs\CollectNotificationJob;
use AnourValar\EloquentNotification\Tests\FooNotification;
use AnourValar\EloquentNotification\Tests\BazNotification;

class CollectNotificationJobTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @return void
     */
    public function test_handle_1()
    {
        \Notification::fake();
        $user = tap((new \AnourValar\EloquentNotification\Tests\User()))->save();
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user->id, 'trigger' => 'foo', 'channels' => ['mail']]);

        $mock = \Atom::partialMock();
        $arg = [
            ['notification' => FooNotification::class, 'arguments' => ['foo'], 'group_by' => ''],
        ];
        $mock->shouldReceive('exchangerPull')->once()->with("eloquent_notification:collect_notify:{$user->id}")->andReturn($arg);

        dispatch(new CollectNotificationJob($user));

        \Notification::assertSentTimes(FooNotification::class, 1);
        \Notification::assertSentTo(
            $user,
            FooNotification::class,
            fn ($notification, $channels) => $notification->arg1 == ['foo'] && $notification->arg2 == []
        );
    }

    /**
     * @return void
     */
    public function test_handle_2()
    {
        \Notification::fake();
        $user = tap((new \AnourValar\EloquentNotification\Tests\User()))->save();
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user->id, 'trigger' => 'foo', 'channels' => ['mail']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user->id, 'trigger' => 'baz', 'channels' => ['mail']]);

        $mock = \Atom::partialMock();
        $arg = [
            ['notification' => FooNotification::class, 'arguments' => ['foo-1', 'foo-a'], 'group_by' => ''],
            ['notification' => BazNotification::class, 'arguments' => ['baz-1'], 'group_by' => ''],
            ['notification' => FooNotification::class, 'arguments' => ['foo-2', 'foo-b'], 'group_by' => ''],
            ['notification' => BazNotification::class, 'arguments' => ['baz-2'], 'group_by' => ''],
            ['notification' => BazNotification::class, 'arguments' => ['baz-3'], 'group_by' => ''],
            ['notification' => FooNotification::class, 'arguments' => ['foo-3', 'foo-c'], 'group_by' => 'alt'],
        ];
        $mock->shouldReceive('exchangerPull')->once()->with("eloquent_notification:collect_notify:{$user->id}")->andReturn($arg);

        dispatch(new CollectNotificationJob($user));

        \Notification::assertSentTimes(FooNotification::class, 2);
        \Notification::assertSentTo(
            $user,
            FooNotification::class,
            fn ($notification, $channels) => $notification->arg1 == ['foo-1', 'foo-2'] && $notification->arg2 == ['foo-a', 'foo-b']
        );
        \Notification::assertSentTo(
            $user,
            FooNotification::class,
            fn ($notification, $channels) => $notification->arg1 == ['foo-3'] && $notification->arg2 == ['foo-c']
        );

        \Notification::assertSentTimes(BazNotification::class, 1);
        \Notification::assertSentTo(
            $user,
            BazNotification::class,
            fn ($notification, $channels) => $notification->arg1 == ['baz-1', 'baz-2', 'baz-3'] && $notification->arg2 == []
        );
    }
}
